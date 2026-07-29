<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    use ResolvesClient;

    /**
     * Quote statuses a client is allowed to see (drafts stay internal).
     */
    private const VISIBLE_STATUSES = ['Sent', 'Accepted', 'Declined', 'Expired'];

    public function index()
    {
        $quotes = Quote::where('client_id', $this->getClientId())
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->orderByDesc('id')
            ->paginate(25);

        return view('client.quotes.index', compact('quotes'));
    }

    public function show(Quote $quote)
    {
        $this->authorizeQuote($quote);
        $quote->load('items');

        return view('client.quotes.show', [
            'quote'     => $quote,
            'actionable' => $this->isActionable($quote),
        ]);
    }

    /**
     * Client accepts the quote: converts it to an unpaid invoice and takes
     * the client straight to payment.
     */
    public function accept(Quote $quote, QuoteService $quoteService)
    {
        $this->authorizeQuote($quote);

        if (!$this->isActionable($quote)) {
            return back()->with('error', __('client.quotes.not_actionable'));
        }

        $invoice = $quoteService->convertToInvoice($quote);

        run_hook('QuoteAccepted', ['quote' => $quote->fresh(), 'invoice' => $invoice]);

        return redirect()
            ->route('client.invoices.show', $invoice)
            ->with('success', __('client.quotes.accepted_redirect'));
    }

    public function decline(Request $request, Quote $quote, QuoteService $quoteService)
    {
        $this->authorizeQuote($quote);

        if (!$this->isActionable($quote)) {
            return back()->with('error', __('client.quotes.not_actionable'));
        }

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $quoteService->declineQuote($quote);
        if (!empty($validated['reason'])) {
            $quote->update(['customer_notes' => $validated['reason']]);
        }

        run_hook('QuoteDeclined', ['quote' => $quote->fresh(), 'reason' => $validated['reason'] ?? null]);

        return redirect()
            ->route('client.quotes.index')
            ->with('success', __('client.quotes.declined_message'));
    }

    private function isActionable(Quote $quote): bool
    {
        if ($quote->status !== 'Sent') {
            return false;
        }

        $validUntil = $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until) : null;

        return !$validUntil || $validUntil->endOfDay()->isFuture();
    }

    private function authorizeQuote(Quote $quote): void
    {
        abort_if($quote->client_id !== $this->getClientId(), 403);
        abort_if(!in_array($quote->status, self::VISIBLE_STATUSES, true), 404);
    }

}
