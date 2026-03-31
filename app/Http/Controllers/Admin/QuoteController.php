<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $quoteService) {}

    public function index(Request $request)
    {
        $query = Quote::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', '%' . $search . '%')
                  ->orWhereHas('client', fn ($c) =>
                      $c->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                  );
            });
        }

        $quotes = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.quotes.index', compact('quotes'));
    }

    public function create()
    {
        $clients = Client::orderBy('first_name')->get();
        return view('admin.quotes.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'subject'          => 'required|string|max:255',
            'date'             => 'required|date',
            'valid_until'      => 'required|date|after_or_equal:date',
            'notes'            => 'nullable|string',
            'customer_notes'   => 'nullable|string',
            'proposal'         => 'nullable|string',
            'items'            => 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity'    => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'  => 'required_with:items|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'items.*.taxable'     => 'nullable|boolean',
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $quote  = $this->quoteService->createQuote($client, $validated);

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote created successfully.');
    }

    public function show(Quote $quote)
    {
        $quote->load('client', 'items');
        return view('admin.quotes.show', compact('quote'));
    }

    public function edit(Quote $quote)
    {
        $quote->load('items');
        $clients = Client::orderBy('first_name')->get();
        return view('admin.quotes.edit', compact('quote', 'clients'));
    }

    public function update(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'subject'          => 'required|string|max:255',
            'date'             => 'required|date',
            'valid_until'      => 'required|date|after_or_equal:date',
            'notes'            => 'nullable|string',
            'customer_notes'   => 'nullable|string',
            'proposal'         => 'nullable|string',
            'items'            => 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity'    => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'  => 'required_with:items|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'items.*.taxable'     => 'nullable|boolean',
        ]);

        $this->quoteService->updateQuote($quote, $validated);

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote updated successfully.');
    }

    public function destroy(Quote $quote)
    {
        $this->quoteService->deleteQuote($quote);
        return redirect()->route('admin.quotes.index')
            ->with('success', 'Quote deleted.');
    }

    public function send(Quote $quote)
    {
        $this->quoteService->sendQuote($quote);
        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote marked as Sent.');
    }

    public function convertToInvoice(Quote $quote)
    {
        $invoice = $this->quoteService->convertToInvoice($quote);
        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Quote converted to invoice successfully.');
    }

    public function accept(Quote $quote)
    {
        $this->quoteService->acceptQuote($quote);
        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote accepted.');
    }

    public function decline(Quote $quote)
    {
        $this->quoteService->declineQuote($quote);
        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote declined.');
    }
}
