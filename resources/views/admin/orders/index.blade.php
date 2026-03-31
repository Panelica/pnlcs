@extends("admin.layouts.app")
@section("title", "Orders")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Orders</h1>
</div>
<div class="flex gap-2 mb-6">
    @foreach(["" => "All", "pending" => "Pending", "active" => "Active", "fraud" => "Fraud", "cancelled" => "Cancelled"] as $val => $label)
    <a href="{{ route("admin.orders.index", ["status" => $val]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request("status") == $val ? "bg-indigo-600 text-white" : "bg-slate-100 dark:bg-slate-700 hover:bg-slate-200" }}">{{ $label }}</a>
    @endforeach
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left font-medium">Order #</th><th class="px-4 py-3 text-left font-medium">Client</th><th class="px-4 py-3 text-left font-medium">Date</th><th class="px-4 py-3 text-right font-medium">Amount</th><th class="px-4 py-3 text-left font-medium">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($orders as $order)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 font-mono text-xs"><a href="{{ route("admin.orders.show", $order) }}" class="text-indigo-600">#{{ $order->order_num }}</a></td>
                <td class="px-4 py-3">{{ $order->client->full_name ?? "N/A" }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $order->date?->format("d M Y") }}</td>
                <td class="px-4 py-3 text-right">${{ number_format($order->amount, 2) }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $order->status == "active" ? "bg-emerald-100 text-emerald-700" : ($order->status == "pending" ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($order->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $orders->withQueryString()->links() }}</div>
</div>
@endsection
