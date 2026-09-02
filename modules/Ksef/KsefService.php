<?php

namespace Modules\Ksef;

use App\Models\Invoice;
use App\Models\KsefInvoice;
use App\Models\ModuleLog;
use App\Services\AddonManager;
use Illuminate\Support\Facades\Log;
use Modules\Ksef\Jobs\SubmitInvoiceJob;

/**
 * Business logic for the KSeF addon: decides which invoices go to KSeF and
 * drives their lifecycle through the KSeF API.
 */
class KsefService
{
    public function __construct(
        protected KsefClient $client,
        protected AddonManager $addons,
    ) {}

    /** Whether the addon is active and configured. */
    public function enabled(): bool
    {
        if (! $this->addons->isActive('ksef')) {
            return false;
        }

        return KsefSettings::configured();
    }

    /**
     * Queue an invoice for KSeF submission.
     *
     * Called from the InvoicePaid hook. Only invoices in the configured
     * send_statuses (paid) are queued; a correction invoice still submits its
     * own row (linked to the original via corrected_by_invoice_id).
     */
    public function queueForInvoice(Invoice $invoice): ?KsefInvoice
    {
        if (! $this->enabled()) {
            return null;
        }

        $allowed = config('ksef.send_statuses', ['paid']);
        if (! in_array((string) $invoice->status, $allowed, true)) {
            return null;
        }

        // Only VAT invoices go to KSeF — a proforma is never submitted. The
        // type column is added by the Proforma feature; without it every
        // invoice is treated as a VAT invoice.
        if (($invoice->type ?? 'vat') !== 'vat') {
            return null;
        }

        $record = KsefInvoice::firstOrCreate(
            ['invoice_id' => $invoice->id],
            ['status' => 'pending'],
        );

        // Hand the invoice to KSeF in the background: the external API must
        // never block the payment request. The scheduler picks the job up.
        SubmitInvoiceJob::dispatch($record->id);

        return $record->fresh();
    }

    /**
     * Submit a queued invoice to KSeF.
     *
     * @return array{success: bool, message: string}
     */
    public function send(KsefInvoice $record): array
    {
        $record->increment('attempts');

        try {
            $result = $this->client->submit($record->invoice, $record);

            if ($result['success'] ?? false) {
                $record->update([
                    'session_reference' => $result['session_reference'] ?? null,
                    'status' => $result['status'] ?? 'sent',
                    'ksef_number' => $result['ksef_number'] ?? null,
                    'sent_at' => $result['sent_at'] ?? now(),
                    'accepted_at' => $result['accepted'] ? now() : null,
                    'request_xml' => $result['request_xml'] ?? null,
                    'response_xml' => $result['response_xml'] ?? null,
                    'error_message' => $result['error_message'] ?? null,
                ]);

                $this->logAction('submit', ['invoice_id' => $record->invoice_id], ['success' => true, 'status' => $result['status'] ?? 'sent', 'ksef_number' => $result['ksef_number'] ?? null, 'error' => $result['error_message'] ?? null]);

                return ['success' => true, 'message' => $result['message'] ?? 'Sent'];
            }

            $record->update([
                'status' => 'error',
                'error_message' => $result['message'] ?? 'KSeF rejected the submission',
                'request_xml' => $result['request_xml'] ?? null,
                'response_xml' => $result['response_xml'] ?? null,
            ]);

            $this->logAction('submit', ['invoice_id' => $record->invoice_id], ['success' => false, 'error' => $result['message'] ?? null]);

            return ['success' => false, 'message' => $result['message'] ?? 'Unknown error'];
        } catch (\Throwable $e) {
            Log::error('KSeF: submission failed', [
                'invoice_id' => $record->invoice_id,
                'error' => $e->getMessage(),
            ]);

            $record->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            $this->logAction('submit', ['invoice_id' => $record->invoice_id], ['success' => false, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Issue a correction (korekta) for an already-submitted invoice.
     *
     * The correction is a new invoice that points back at the original via
     * corrected_by_invoice_id; the original is marked corrected once the
     * correction is accepted.
     */
    public function issueCorrection(KsefInvoice $original, Invoice $correction): ?KsefInvoice
    {
        $record = KsefInvoice::firstOrCreate(
            ['invoice_id' => $correction->id],
            [
                'status' => 'pending',
                'corrected_by_invoice_id' => $original->invoice_id,
            ],
        );

        $this->send($record);

        $original->update(['status' => 'corrected']);

        return $record->fresh();
    }

    /**
     * Write a KSeF action to the module log viewer (System Logs).
     */
    public function logAction(string $action, array $request = [], array|string|null $response = null): void
    {
        try {
            ModuleLog::create([
                'module' => 'KSeF',
                'action' => $action,
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            Log::warning('KSeF module log failed: '.$e->getMessage());
        }
    }
}
