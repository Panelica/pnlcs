<?php

namespace Modules\Ksef;

use App\Models\KsefImportedInvoice;
use App\Models\KsefImportRun;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Ksef\Support\Nip;
use Throwable;

/**
 * Imports the accountant's view of KSeF: invoices we issued (Subject1) and
 * invoices received (Subject2). The local KsefInvoice table remains the
 * submission log; imported documents are a separate read-only registry.
 */
class KsefInvoiceImporter
{
    public function __construct(private readonly KsefClient $client) {}

    /** @return array{status: string, fetched: int, created: int, updated: int, errors: int, message: string} */
    public function import(?CarbonInterface $from = null): array
    {
        $settings = KsefSettings::resolve();

        if (! KsefSettings::configured()) {
            return $this->result('error', 0, 0, 0, 0, __('messages.ksef.missing_credentials'));
        }

        $last = KsefImportRun::where('status', 'ok')->latest('finished_at')->first();
        $firstImport = $from === null && $last === null;
        $from = $from ?? ($firstImport
            ? CarbonImmutable::now()->startOfYear()
            : ($last?->to_date?->toImmutable()->subDay() ?? CarbonImmutable::now()->startOfYear()));
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::now();
        $dateType = $firstImport ? 'Issue' : 'PermanentStorage';

        $run = KsefImportRun::create([
            'started_at' => now(),
            'status' => 'running',
            'date_type' => $dateType,
            'from_date' => $from,
            'to_date' => $to,
        ]);

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        try {
            $token = $this->client->authenticate($settings);
            $endpoint = rtrim((string) $settings['endpoint'], '/');

            foreach ([['Subject1', 'sales'], ['Subject2', 'cost']] as [$subject, $direction]) {
                foreach ($this->ranges($from, $to) as [$rangeFrom, $rangeTo]) {
                    $offset = 0;
                    $pages = 0;

                    do {
                        $response = $this->client->queryInvoiceMetadata(
                            $token,
                            $endpoint,
                            $subject,
                            $rangeFrom->toIso8601String(),
                            $rangeTo->toIso8601String(),
                            $dateType,
                            $offset,
                            100,
                        );

                        $invoices = $response['invoices'] ?? $response['data'] ?? [];
                        if (! is_array($invoices)) {
                            $invoices = [];
                        }

                        foreach ($invoices as $metadata) {
                            if (! is_array($metadata)) {
                                continue;
                            }

                            $fetched++;
                            $ksefNumber = (string) ($metadata['ksefNumber'] ?? $metadata['ksef_number'] ?? '');

                            if ($ksefNumber === '') {
                                $errors++;

                                continue;
                            }

                            try {
                                $xml = $this->client->downloadInvoice($token, $endpoint, $ksefNumber);
                            } catch (Throwable $e) {
                                $xml = null;
                                $errors++;
                                Log::warning('KSeF import: XML download failed', ['ksef_number' => $ksefNumber, 'error' => $e->getMessage()]);
                            }

                            $record = $this->record($metadata, $direction, $xml, $ksefNumber);

                            if ($record === null) {
                                continue;
                            }

                            if ($record->wasRecentlyCreated) {
                                $created++;
                            } else {
                                $updated++;
                            }
                        }

                        $count = count($invoices);
                        $offset += $count;
                        $pages++;
                    } while ($count === 100 && $pages < 100);
                }
            }

            $run->update([
                'finished_at' => now(),
                'status' => $errors > 0 ? 'partial' : 'ok',
                'fetched' => $fetched,
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'message' => __('messages.ksef.import_ok'),
            ]);

            return $this->result($errors > 0 ? 'partial' : 'ok', $fetched, $created, $updated, $errors, __('messages.ksef.import_ok'));
        } catch (Throwable $e) {
            Log::error('KSeF import failed', ['error' => $e->getMessage()]);

            $run->update([
                'finished_at' => now(),
                'status' => 'error',
                'fetched' => $fetched,
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors + 1,
                'message' => $e->getMessage(),
            ]);

            return $this->result('error', $fetched, $created, $updated, $errors + 1, $e->getMessage());
        }
    }

    /**
     * KSeF accepts at most three months in one metadata query. Split a first
     * import (the current year) and any long incremental gap into safe ranges.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function ranges(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ranges = [];
        $start = $from;

        while ($start->lessThanOrEqualTo($to)) {
            $end = $start->addMonthsNoOverflow(3)->subSecond();

            if ($end->greaterThan($to)) {
                $end = $to;
            }

            $ranges[] = [$start, $end];
            $start = $end->addSecond();
        }

        return $ranges;
    }

    private function record(array $metadata, string $direction, ?string $xml, string $ksefNumber): ?KsefImportedInvoice
    {
        $parsed = $this->parseXml($xml);

        $seller = $this->party($metadata, 'seller', 'subject1');
        $buyer = $this->party($metadata, 'buyer', 'subject2');
        $sellerNip = Nip::normalize($seller['nip'] ?? $parsed['seller_nip'] ?? '');
        $ourNip = Nip::normalize((string) KsefSettings::resolve()['nip']);

        // Subject2 should be an invoice issued *to* us. Test/self-billing data
        // can return an invoice where our NIP is both sides; it is not a cost
        // invoice and must not appear in the costs tab.
        if ($direction === 'cost' && $sellerNip !== '' && $sellerNip === $ourNip) {
            return null;
        }

        return KsefImportedInvoice::updateOrCreate(
            ['ksef_number' => $ksefNumber],
            [
                'direction' => $direction,
                'invoice_number' => $this->value($metadata, ['invoiceNumber', 'invoice_number']) ?? $parsed['invoice_number'],
                'issue_date' => $this->date($this->value($metadata, ['issueDate', 'issue_date'])) ?? $parsed['issue_date'],
                'invoicing_date' => $this->date($this->value($metadata, ['invoicingDate', 'invoicing_date'])),
                'permanent_storage_date' => $this->date($this->value($metadata, ['permanentStorageDate', 'permanent_storage_date'])),
                'seller_nip' => $sellerNip,
                'seller_name' => $seller['name'] ?? $parsed['seller_name'],
                'buyer_nip' => Nip::normalize($buyer['nip'] ?? $parsed['buyer_nip'] ?? ''),
                'buyer_name' => $buyer['name'] ?? $parsed['buyer_name'],
                'net' => $this->amount($metadata, ['netAmount', 'net_amount']) ?? $parsed['net'],
                'vat' => $this->amount($metadata, ['vatAmount', 'vat_amount']) ?? $parsed['vat'],
                'gross' => $this->amount($metadata, ['grossAmount', 'gross_amount']) ?? $parsed['gross'],
                'currency' => $this->value($metadata, ['currency', 'currencyCode']) ?? $parsed['currency'],
                'metadata' => $metadata,
                'xml' => $xml,
                'imported_at' => now(),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function party(array $metadata, string $key, string $fallback): array
    {
        $party = $metadata[$key] ?? $metadata[$fallback] ?? [];

        if (! is_array($party)) {
            return [];
        }

        $identifier = $party['identifier'] ?? null;
        $nestedNip = is_array($identifier) ? ($identifier['value'] ?? null) : null;

        return [
            'nip' => $party['nip'] ?? $party['NIP'] ?? $nestedNip,
            'name' => $party['name'] ?? $party['fullName'] ?? $party['Nazwa'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function parseXml(?string $xml): array
    {
        if ($xml === null || trim($xml) === '') {
            return [];
        }

        try {
            $doc = new \DOMDocument;
            $doc->loadXML($xml, LIBXML_NONET);

            $xpath = new \DOMXPath($doc);
            $text = function (string $query) use ($xpath): ?string {
                $node = $xpath->query($query)->item(0);

                return $node ? trim((string) $node->nodeValue) : null;
            };

            $net = 0.0;
            $vat = 0.0;
            foreach ($xpath->query('//*[starts-with(local-name(), "P_13")]') as $node) {
                $net += (float) str_replace(',', '.', trim((string) $node->nodeValue));
            }
            foreach ($xpath->query('//*[starts-with(local-name(), "P_14")]') as $node) {
                $vat += (float) str_replace(',', '.', trim((string) $node->nodeValue));
            }

            return [
                'invoice_number' => $text('//*[local-name()="P_2"]'),
                'issue_date' => $this->date($text('//*[local-name()="P_1"]')),
                'seller_nip' => $text('//*[local-name()="Podmiot1"]//*[local-name()="NIP"]'),
                'seller_name' => $text('//*[local-name()="Podmiot1"]//*[local-name()="Nazwa"]'),
                'buyer_nip' => $text('//*[local-name()="Podmiot2"]//*[local-name()="NIP"]'),
                'buyer_name' => $text('//*[local-name()="Podmiot2"]//*[local-name()="Nazwa"]'),
                'net' => round($net, 2) ?: null,
                'vat' => round($vat, 2) ?: null,
                'gross' => round($net + $vat, 2) ?: null,
                'currency' => $text('//*[local-name()="KodWaluty"]'),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private function value(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function amount(array $data, array $keys): ?float
    {
        $value = $this->value($data, $keys);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function result(string $status, int $fetched, int $created, int $updated, int $errors, string $message): array
    {
        return compact('status', 'fetched', 'created', 'updated', 'errors', 'message');
    }
}
