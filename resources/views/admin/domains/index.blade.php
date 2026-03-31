@extends("admin.layouts.app")
@section("title", "Domains")
@section("content")
<div class="flex items-center justify-between mb-6"><h1 class="text-2xl font-bold">Domains</h1></div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left font-medium">Domain</th><th class="px-4 py-3 text-left font-medium">Client</th><th class="px-4 py-3 text-left font-medium">Registrar</th><th class="px-4 py-3 text-left font-medium">Registration</th><th class="px-4 py-3 text-left font-medium">Expiry</th><th class="px-4 py-3 text-left font-medium">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($domains as $domain)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 font-medium">{{ $domain->domain }}</td>
                <td class="px-4 py-3">{{ $domain->client->full_name ?? "N/A" }}</td>
                <td class="px-4 py-3">{{ $domain->registrar ?? "-" }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $domain->registration_date?->format("d M Y") ?? "-" }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $domain->expiry_date?->format("d M Y") ?? "-" }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $domain->status == "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($domain->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No domains found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $domains->withQueryString()->links() }}</div>
</div>
@endsection
