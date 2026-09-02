<?php

namespace Modules\Ksef\Http\Admin;

use App\Http\Controllers\Controller;
use App\Models\KsefInvoice;
use Modules\Ksef\Jobs\SubmitInvoiceJob;
use Modules\Ksef\KsefClient;
use Modules\Ksef\KsefService;

class KsefController extends Controller
{
    public function __construct(
        protected KsefService $service,
        protected KsefClient $client,
    ) {}

    /**
     * Test the connection to the KSeF API.
     */
    public function test()
    {
        $result = $this->client->testConnection();
        $this->service->logAction('test', [], $result);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            'KSeF: '.$result['message'],
        );
    }

    /**
     * Re-submit a failed or pending invoice to KSeF.
     */
    public function resend(KsefInvoice $record)
    {
        $record->update(['status' => 'pending', 'error_message' => null]);
        SubmitInvoiceJob::dispatch($record->id);

        return back()->with('success', __('messages.ksef.queued'));
    }

    /**
     * Mark an already-sent invoice as replaced by a correction (korekta).
     *
     * The correction itself is issued as a new invoice from the billing
     * screens; this records that link so the KSeF list shows the original as
     * corrected.
     */
    public function markCorrected(KsefInvoice $record)
    {
        $record->update(['status' => 'corrected']);

        return back()->with('success', __('messages.ksef.marked_corrected'));
    }
}
