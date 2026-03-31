@extends("admin.layouts.app")
@section("title", "Invoice #" . $invoice->id)
@section("content")
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Invoice #{{ $invoice->id }}</h1>
            <p class="text-slate-500">{{ $invoice->client->full_name ?? "N/A" }} | {{ $invoice->date?->format("d M Y") }}</p>
        </div>
        <span class="px-3 py-1 text-sm font-medium rounded-full {{ $invoice->status == "paid" ? "bg-emerald-100 text-emerald-700" : ($invoice->status == "unpaid" ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($invoice->status) }}</span>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="py-2 text-left">Description</th>
                    <th class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                <tr class="border-b border-slate-100 dark:border-slate-700/50">
                    <td class="py-3">{{ $item->description }}</td>
                    <td class="py-3 text-right">${{ number_format($item->amount, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2" class="py-4 text-center text-slate-400">No line items</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-300 dark:border-slate-600">
                    <td class="py-3 font-semibold">Subtotal</td>
                    <td class="py-3 text-right font-semibold">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->tax > 0)
                <tr><td class="py-1">Tax</td><td class="py-1 text-right">${{ number_format($invoice->tax, 2) }}</td></tr>
                @endif
                <tr class="text-lg"><td class="py-3 font-bold">Total</td><td class="py-3 text-right font-bold">${{ number_format($invoice->total, 2) }}</td></tr>
            </tfoot>
        </table>
    </div>

    @if($invoice->transactions->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Transactions</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="py-2 text-left">Date</th><th class="py-2 text-left">Gateway</th><th class="py-2 text-left">Transaction ID</th><th class="py-2 text-right">Amount</th></tr></thead>
            <tbody>
                @foreach($invoice->transactions as $tx)
                <tr class="border-b border-slate-100"><td class="py-2">{{ $tx->date?->format("d M Y") }}</td><td class="py-2">{{ $tx->gateway }}</td><td class="py-2 font-mono text-xs">{{ $tx->transaction_id }}</td><td class="py-2 text-right">${{ number_format($tx->amount_in, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
