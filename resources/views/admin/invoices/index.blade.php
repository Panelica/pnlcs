@extends("admin.layouts.app")
@section("title", "Invoices")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Invoices</h1>
</div>

<div class="flex gap-2 mb-6 flex-wrap">
    @foreach(["" => "All", "unpaid" => "Unpaid", "paid" => "Paid", "overdue" => "Overdue", "cancelled" => "Cancelled", "refunded" => "Refunded", "draft" => "Draft"] as $val => $label)
    <a href="{{ route("admin.invoices.index", ["status" => $val]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request("status") == $val ? "bg-indigo-600 text-white" : "bg-slate-100 dark:bg-slate-700 hover:bg-slate-200" }} transition-colors">{{ $label }}</a>
    @endforeach
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Invoice #</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Client</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Due Date</th>
                <th class="px-4 py-3 text-right font-medium text-slate-600">Total</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($invoices as $invoice)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3"><a href="{{ route("admin.invoices.show", $invoice) }}" class="text-indigo-600 hover:text-indigo-500 font-mono">#{{ $invoice->id }}</a></td>
                <td class="px-4 py-3">{{ $invoice->client->full_name ?? "N/A" }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $invoice->date?->format("d M Y") }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date?->format("d M Y") }}</td>
                <td class="px-4 py-3 text-right font-medium">${{ number_format($invoice->total, 2) }}</td>
                <td class="px-4 py-3">
                    @php $colors = ["paid" => "emerald", "unpaid" => "amber", "overdue" => "red", "cancelled" => "slate", "draft" => "slate", "refunded" => "violet"]; $c = $colors[$invoice->status] ?? "slate"; @endphp
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">{{ ucfirst($invoice->status) }}</span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No invoices found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $invoices->withQueryString()->links() }}</div>
</div>
@endsection
