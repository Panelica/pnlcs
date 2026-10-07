<?php

namespace App\Services\WhmcsImport;

use App\Enums\ClientStatus;

/**
 * Checks a mapping before any row is touched, and each resolved row before it
 * is written. A bad configuration must fail here, not halfway through an import.
 */
class ImportValidator
{
    public const MODES = ['add', 'add_update', 'update'];

    /**
     * @param  array<string, mixed>  $mapping  ['columns' => ..., 'constants' => ...]
     * @param  list<string>  $targetFields  importable PNLCS fields
     * @param  list<string>  $sourceColumns  WHMCS columns present
     * @param  list<string>  $requiredFields  target fields that must be mapped when adding rows
     * @return list<string> human-readable problems (empty when the mapping is sane)
     */
    public function mapping(
        array $mapping,
        array $targetFields,
        array $sourceColumns,
        ?string $matchKey,
        string $importMode,
        array $requiredFields = ['first_name', 'last_name', 'email'],
    ): array {
        $errors = [];

        if (! in_array($importMode, self::MODES, true)) {
            $errors[] = __('whmcs_import.validation.invalid_mode');
        }

        $columns = $mapping['columns'] ?? [];
        $constants = $mapping['constants'] ?? [];

        $mappedTargets = array_values(array_filter($columns, fn ($t) => is_string($t) && $t !== ''));
        foreach ($constants as $field => $value) {
            if (! is_string($value) || $value === '') {
                $errors[] = __('whmcs_import.validation.empty_constant', ['field' => $field]);
            }
        }
        $mappedTargets = array_values(array_unique(array_merge($mappedTargets, array_keys($constants))));

        // A target that is not on the model cannot be written at all.
        $unknown = array_diff($mappedTargets, $targetFields);
        foreach ($unknown as $field) {
            $errors[] = __('whmcs_import.validation.unknown_target', ['field' => $field]);
        }

        // A source that does not exist in the live table is a typo or a stale profile.
        $missingSources = array_diff(array_keys($columns), $sourceColumns);
        foreach ($missingSources as $source) {
            $errors[] = __('whmcs_import.validation.unknown_source', ['source' => $source]);
        }

        // Adding rows needs the non-null columns; updating can leave them alone.
        if (in_array($importMode, ['add', 'add_update'], true)) {
            foreach ($requiredFields as $required) {
                if (! in_array($required, $mappedTargets, true)) {
                    $errors[] = __('whmcs_import.validation.required_unmapped', ['field' => $required]);
                }
            }
        }

        if (in_array($importMode, ['add_update', 'update'], true) && ($matchKey === null || $matchKey === '')) {
            $errors[] = __('whmcs_import.validation.match_key_required');
        }

        if ($matchKey !== null && $matchKey !== '' && ! in_array($matchKey, $mappedTargets, true)) {
            $errors[] = __('whmcs_import.validation.match_key_unmapped', ['field' => $matchKey]);
        }

        // Regex transforms must be well-formed before any row is touched.
        foreach (($mapping['transforms'] ?? []) as $field => $transform) {
            $pattern = (string) ($transform['pattern'] ?? '');
            if ($pattern === '') {
                continue;
            }
            if (@preg_match($pattern, '') === false) {
                $errors[] = __('whmcs_import.validation.invalid_regex', ['field' => $field]);
            }
        }

        return $errors;
    }

    /**
     * Validate one resolved target row. Returns an error message or null.
     *
     * @param  array<string, mixed>  $target
     * @param  list<string>  $requiredFields  fields that must not be empty when creating
     * @param  list<string>|null  $allowedStatuses  valid status values, defaults to the client set
     */
    public function record(array $target, bool $creating, array $requiredFields = ['first_name', 'last_name', 'email'], ?array $allowedStatuses = null): ?string
    {
        if ($creating) {
            foreach ($requiredFields as $required) {
                if (trim((string) ($target[$required] ?? '')) === '') {
                    return __('whmcs_import.validation.missing', ['field' => $required]);
                }
            }

            if (isset($target['email']) && ! filter_var($target['email'], FILTER_VALIDATE_EMAIL)) {
                return __('whmcs_import.validation.invalid_email', ['value' => $target['email']]);
            }
        }

        if (isset($target['status']) && $target['status'] !== '') {
            $valid = $allowedStatuses ?? array_map(fn (ClientStatus $s) => $s->value, ClientStatus::cases());
            if (! in_array($target['status'], $valid, true)) {
                return __('whmcs_import.validation.invalid_status', ['value' => $target['status']]);
            }
        }

        if (isset($target['country']) && strlen((string) $target['country']) > 2) {
            return __('whmcs_import.validation.country_too_long', ['value' => $target['country']]);
        }

        return null;
    }
}
