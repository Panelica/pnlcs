@extends("client.layouts.app")
@section("title", "My Domains")
@section("content")
<h1 class="text-2xl font-bold mb-6">My Domains</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left">Domain</th><th class="px-4 py-3 text-left">Registration</th><th class="px-4 py-3 text-left">Expiry</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($domains as $d)
            <tr><td class="px-4 py-3 font-medium">{{ $d->domain }}</td><td class="px-4 py-3">{{ $d->registration_date?->format("d M Y") }}</td><td class="px-4 py-3">{{ $d->expiry_date?->format("d M Y") }}</td><td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full {{ $d->status == "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($d->status) }}</span></td></tr>
            @empty
            <tr><td colspan="4" class="px-4 py-12 text-center text-slate-400">No domains found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
