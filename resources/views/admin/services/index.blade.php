@extends("admin.layouts.app")
@section("title", "Services")
@section("content")
<div class="flex items-center justify-between mb-6"><h1 class="text-2xl font-bold">Products/Services</h1></div>
<div class="flex gap-2 mb-6">
    @foreach(["" => "All", "active" => "Active", "suspended" => "Suspended", "terminated" => "Terminated", "pending" => "Pending", "cancelled" => "Cancelled"] as $val => $label)
    <a href="{{ route("admin.services.index", ["status" => $val]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request("status") == $val ? "bg-indigo-600 text-white" : "bg-slate-100 dark:bg-slate-700 hover:bg-slate-200" }}">{{ $label }}</a>
    @endforeach
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left font-medium">ID</th><th class="px-4 py-3 text-left font-medium">Client</th><th class="px-4 py-3 text-left font-medium">Product</th><th class="px-4 py-3 text-left font-medium">Domain</th><th class="px-4 py-3 text-right font-medium">Amount</th><th class="px-4 py-3 text-left font-medium">Next Due</th><th class="px-4 py-3 text-left font-medium">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($services as $service)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 font-mono text-xs">{{ $service->id }}</td>
                <td class="px-4 py-3">{{ $service->client->full_name ?? "N/A" }}</td>
                <td class="px-4 py-3">{{ $service->product->name ?? "N/A" }}</td>
                <td class="px-4 py-3">{{ $service->domain ?? "-" }}</td>
                <td class="px-4 py-3 text-right">${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $service->next_due_date?->format("d M Y") ?? "-" }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $service->status == "active" ? "bg-emerald-100 text-emerald-700" : ($service->status == "suspended" ? "bg-red-100 text-red-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($service->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No services found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $services->withQueryString()->links() }}</div>
</div>
@endsection
