@extends("client.layouts.app")
@section("title", "My Services")
@section("content")
<h1 class="text-2xl font-bold mb-6">My Services</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">Domain</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-left">Next Due</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($services as $s)
            <tr><td class="px-4 py-3"><a href="{{ route("client.services.show", $s) }}" class="text-indigo-600">{{ $s->product->name ?? "N/A" }}</a></td><td class="px-4 py-3">{{ $s->domain ?? "-" }}</td><td class="px-4 py-3 text-right">${{ number_format($s->amount,2) }}/{{ $s->billing_cycle }}</td><td class="px-4 py-3">{{ $s->next_due_date?->format("d M Y") ?? "-" }}</td><td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full {{ $s->status == "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($s->status) }}</span></td></tr>
            @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">No services found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
