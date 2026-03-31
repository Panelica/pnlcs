@extends('admin.layouts.app')
@section('title', 'Domains')
@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Domains</h1>
        <span class="text-sm text-slate-500">{{ $domains->total() }} total</span>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.domains.index') }}" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search domain..."
               class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">

        <select name="status" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            @foreach($statuses as $s)
            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>

        @if($registrars->count() > 0)
        <select name="registrar" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Registrars</option>
            @foreach($registrars as $r)
            <option value="{{ $r }}" {{ request('registrar') == $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
            @endforeach
        </select>
        @endif

        <select name="sort" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Sort: Created</option>
            <option value="expiry_date" {{ request('sort') == 'expiry_date' ? 'selected' : '' }}>Sort: Expiry</option>
            <option value="registration_date" {{ request('sort') == 'registration_date' ? 'selected' : '' }}>Sort: Registration</option>
            <option value="domain" {{ request('sort') == 'domain' ? 'selected' : '' }}>Sort: Domain</option>
        </select>

        <select name="dir" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="desc" {{ request('dir', 'desc') == 'desc' ? 'selected' : '' }}>Desc</option>
            <option value="asc" {{ request('dir', 'desc') == 'asc' ? 'selected' : '' }}>Asc</option>
        </select>

        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
        @if(request()->hasAny(['search', 'status', 'registrar', 'sort']))
        <a href="{{ route('admin.domains.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Reset</a>
        @endif
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Domain</th>
                    <th class="px-4 py-3 text-left font-medium">Client</th>
                    <th class="px-4 py-3 text-left font-medium">Registrar</th>
                    <th class="px-4 py-3 text-left font-medium">Type</th>
                    <th class="px-4 py-3 text-left font-medium">Registration</th>
                    <th class="px-4 py-3 text-left font-medium">Expiry</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                    <th class="px-4 py-3 text-left font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($domains as $domain)
                @php
                    $expirySoon = $domain->expiry_date && $domain->expiry_date->diffInDays(now()) <= 30 && $domain->expiry_date->isFuture();
                    $expired = $domain->expiry_date && $domain->expiry_date->isPast();
                @endphp
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="px-4 py-3 font-medium font-mono">{{ $domain->domain }}</td>
                    <td class="px-4 py-3">
                        @if($domain->client)
                        <a href="{{ route('admin.clients.show', $domain->client_id) }}" class="text-indigo-600 hover:underline">{{ $domain->client->full_name }}</a>
                        @else N/A @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ ucfirst($domain->registrar ?? '-') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $domain->type }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $domain->registration_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3 {{ $expired ? 'text-red-600 font-semibold' : ($expirySoon ? 'text-amber-600 font-semibold' : 'text-slate-500') }}">
                        {{ $domain->expiry_date?->format('d M Y') ?? '-' }}
                        @if($expirySoon) <span class="text-xs">(expires soon)</span> @endif
                        @if($expired) <span class="text-xs">(expired)</span> @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $sc = match(strtolower($domain->status)) {
                                'active' => 'bg-emerald-100 text-emerald-700',
                                'pending' => 'bg-amber-100 text-amber-700',
                                'expired' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $sc }}">{{ ucfirst($domain->status) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.domains.show', $domain) }}" class="text-indigo-600 hover:underline text-xs">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No domains found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $domains->withQueryString()->links() }}</div>
    </div>
</div>
@endsection
