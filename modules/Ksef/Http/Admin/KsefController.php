<?php

namespace Modules\Ksef\Http\Admin;

use App\Http\Controllers\Controller;
use App\Models\KsefImportedInvoice;
use App\Models\KsefInvoice;
use Illuminate\Http\Request;
use Modules\Ksef\Jobs\ImportKsefInvoicesJob;
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
     * The submission status list, shown under Billing → KSeF status.
     */
    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['sent', 'sales', 'costs'], true)
            ? (string) $request->input('tab')
            : 'sent';

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($year < 2000 || $year > (int) now()->year + 1) {
            $year = (int) now()->year;
        }
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        if ($tab === 'sent') {
            $records = KsefInvoice::with(['invoice.client', 'correction'])
                ->whereYear('sent_at', $year)
                ->whereMonth('sent_at', $month)
                ->orderByDesc('sent_at')
                ->paginate(20);
        } else {
            $records = KsefImportedInvoice::query()
                ->where('direction', $tab === 'sales' ? 'sales' : 'cost')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->orderByDesc('issue_date')
                ->paginate(20);
        }

        return view('admin.ksef.index', [
            'records' => $records,
            'tab' => $tab,
            'year' => $year,
            'month' => $month,
            'years' => range((int) now()->year + 1, 2020),
            'months' => range(1, 12),
        ]);
    }

    public function import()
    {
        ImportKsefInvoicesJob::dispatch();

        return back()->with('success', __('messages.ksef.import_queued'));
    }

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
