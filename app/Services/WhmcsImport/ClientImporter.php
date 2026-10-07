<?php

namespace App\Services\WhmcsImport;

use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\User;
use App\Services\PasswordResetSender;
use Illuminate\Support\Str;

/**
 * Runs the clients import. Read-only on the source, writes only to PNLCS, and
 * applies the three modes the operator picks: add new, add + update, or update
 * only, matched by a chosen key (email by default, but the operator decides).
 */
class ClientImporter
{
    public function __construct(
        protected MappingEngine $engine,
        protected ImportValidator $validator,
    ) {}

    /**
     * @param  callable(callable(array<string, mixed>): void): void  $rows  yields each source row to its callback
     * @param  array<string, mixed>  $mapping  ['columns' => ..., 'constants' => ...]
     * @return array{total: int, added: int, updated: int, skipped: int, errors: int, error_details: list<array<string, mixed>>}
     */
    public function run(
        callable $rows,
        array $mapping,
        string $importMode,
        ?string $matchKey,
    ): array {
        $summary = [
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
            'skipped_details' => [],
        ];

        $rows(function (array $row) use ($mapping, $importMode, $matchKey, &$summary) {
            $summary['total']++;

            $target = $this->engine->apply($row, $mapping);
            $this->fillMissingNames($target);
            $this->detectClientType($target);

            $creating = ! $this->clientExists($matchKey, $target);
            $problem = $this->validator->record($target, $creating, ['email']);
            if ($problem !== null) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $problem);

                return;
            }

            try {
                [$client, $wasCreated, $skipReason] = $this->persist($target, $matchKey, $importMode);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $e->getMessage());

                return;
            }

            if ($client === null) {
                $summary['skipped']++;
                $summary['skipped_details'][] = $this->error($row, $skipReason ?? '');

                return;
            }

            $wasCreated ? $summary['added']++ : $summary['updated']++;
        });

        return $summary;
    }

    /** Whether a client already exists under the chosen match key. */
    protected function clientExists(?string $matchKey, array $target): bool
    {
        if ($matchKey === null || $matchKey === '') {
            return false;
        }

        $value = $target[$matchKey] ?? null;

        return $value !== null && $value !== ''
            && Client::where($matchKey, $value)->exists();
    }

    /**
     * A company stored with no personal name still has an identity — the
     * company name. Fill the non-null name columns from it (or a placeholder)
     * instead of dropping the client.
     */
    protected function fillMissingNames(array &$target): void
    {
        $company = trim((string) ($target['company_name'] ?? ''));

        if (trim((string) ($target['first_name'] ?? '')) === '') {
            $target['first_name'] = $company !== '' ? $company : '-';
        }

        if (trim((string) ($target['last_name'] ?? '')) === '') {
            $target['last_name'] = '';
        }
    }

    /**
     * A client who has a tax number (NIP/VAT) is a company. Only auto-detect
     * when the mapping did not set a type itself, so an explicit client_type
     * mapping still wins.
     */
    protected function detectClientType(array &$target): void
    {
        if (trim((string) ($target['client_type'] ?? '')) === '' && trim((string) ($target['tax_id'] ?? '')) !== '') {
            $target['client_type'] = 'company';
        }
    }

    /**
     * @return array{0: Client|null, 1: bool, 2: string|null} the affected client (null = skipped), whether it was created, and the skip reason
     */
    protected function persist(array $target, ?string $matchKey, string $importMode): array
    {
        [$columns, $customValues] = $this->splitTarget($target);

        $existing = null;
        if ($matchKey !== null && $matchKey !== '') {
            $value = $columns[$matchKey] ?? null;
            if ($value !== null && $value !== '') {
                $existing = Client::where($matchKey, $value)->first();
            }
        }

        if ($existing !== null) {
            if ($importMode === 'add') {
                return [null, false, __('whmcs_import.log.skip_exists')];
            }

            $existing->update($columns);
            $this->storeCustomValues($existing, $customValues);

            return [$existing, false, null];
        }

        if ($importMode === 'update') {
            return [null, false, __('whmcs_import.log.skip_not_found')];
        }

        $client = Client::create($columns);
        $this->storeCustomValues($client, $customValues);
        $this->createLoginUser($client);

        return [$client, true, null];
    }

    /**
     * Separate the model's mass-assignable columns from PNLCS client custom
     * fields, which live in their own table and are written separately.
     *
     * @param  array<string, mixed>  $target
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function splitTarget(array $target): array
    {
        $columns = [];
        $custom = [];

        foreach ($target as $key => $value) {
            if (str_starts_with($key, 'custom_field:')) {
                $custom[substr($key, strlen('custom_field:'))] = $value;
            } else {
                $columns[$key] = $value;
            }
        }

        return [$columns, $custom];
    }

    /**
     * @param  array<string, mixed>  $customValues  field name => value
     */
    protected function storeCustomValues(Client $client, array $customValues): void
    {
        foreach ($customValues as $name => $value) {
            $field = CustomField::where('type', 'client')->where('field_name', $name)->first();
            if (! $field) {
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                CustomFieldValue::where('field_id', $field->id)->where('rel_id', $client->id)->delete();

                continue;
            }

            CustomFieldValue::updateOrCreate(
                ['field_id' => $field->id, 'rel_id' => $client->id],
                ['value' => (string) $value],
            );
        }
    }

    /**
     * The client area signs in as a User, not as a Client. Give an imported
     * client a login (an existing one when the address already has one) and, for
     * a fresh one, a reset link so the customer can choose their own password.
     */
    protected function createLoginUser(Client $client): void
    {
        $email = trim((string) $client->email);
        if ($email === '') {
            return;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $email,
                // A placeholder hash: the reset link is the customer's way in.
                'password' => Str::random(32),
                'is_active' => true,
            ]);

            $client->users()->attach($user->id, ['owner' => true]);

            try {
                app(PasswordResetSender::class)->send($email);
            } catch (\Throwable $e) {
                // A reset mail that cannot be sent must not lose the import.
            }

            return;
        }

        if (! $client->users()->where('users.id', $user->id)->exists()) {
            $client->users()->attach($user->id, ['owner' => false]);
        }
    }

    protected function error(array $row, string $message): array
    {
        return [
            'whmcs_id' => $row['id'] ?? null,
            'email' => $row['email'] ?? null,
            'error' => $message,
        ];
    }
}
