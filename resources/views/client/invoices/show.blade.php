@extends("client.layouts.app")
@section("title", "Invoice #" . $invoice->id)
@section("content")
<h1 class="text-2xl font-bold mb-6">Invoice #{{ $invoice->id }}</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <div class="flex justify-between mb-6"><div><p class="text-sm text-slate-500">Date: {{ $invoice->date?->format("d M Y") }}</p><p class="text-sm text-slate-500">Due: {{ $invoice->due_date?->format("d M Y") }}</p></div><span class="px-3 py-1 text-sm rounded-full {{ $invoice->status == "paid" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700" }}">{{ ucfirst($invoice->status) }}</span></div>
    <table class="w-full text-sm mb-6"><thead><tr class="border-b"><th class="py-2 text-left">Description</th><th class="py-2 text-right">Amount</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr class="border-b border-slate-100"><td class="py-2">{{ $item->description }}</td><td class="py-2 text-right">${{ number_format($item->amount,2) }}</td></tr>@endforeach</tbody><tfoot><tr class="border-t-2 font-bold"><td class="py-3">Total</td><td class="py-3 text-right">${{ number_format($invoice->total,2) }}</td></tr></tfoot></table>
    @if($invoice->status == "unpaid" || $invoice->status == "overdue")
    <a href="#" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Pay Now</a>
    @endif
</div>
@endsection
