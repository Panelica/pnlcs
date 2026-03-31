@extends("client.layouts.app")
@section("title", "My Invoices")
@section("content")
<h1 class="text-2xl font-bold mb-6">My Invoices</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left">Invoice #</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Due Date</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($invoices as $inv)
            <tr><td class="px-4 py-3"><a href="{{ route("client.invoices.show", $inv) }}" class="text-indigo-600">#{{ $inv->id }}</a></td><td class="px-4 py-3">{{ $inv->date?->format("d M Y") }}</td><td class="px-4 py-3">{{ $inv->due_date?->format("d M Y") }}</td><td class="px-4 py-3 text-right font-medium">${{ number_format($inv->total,2) }}</td><td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full {{ $inv->status == "paid" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700" }}">{{ ucfirst($inv->status) }}</span></td></tr>
            @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">No invoices found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
