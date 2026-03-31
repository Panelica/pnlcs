@extends('admin.layouts.app')
@section('title', $domain->domain)
@section('content')
<div class="max-w-4xl space-y-6">

    @if(session('success'))
    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-xl text-emerald-700 dark:text-emerald-300 text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-xl text-red-700 dark:text-red-300 text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <div class="mb-1">
                <a href="{{ route('admin.domains.index') }}" class="text-slate-400 hover:text-slate-600 text-sm">&larr; Domains</a>
            </div>
            <h1 class="text-2xl font-bold font-mono">{{ $domain->domain }}</h1>
            <p class="text-slate-500 mt-1">
                {{ ucfirst($domain->type) }} &mdash; Registrar: {{ ucfirst($domain->registrar ?? 'N/A') }}
                @if($domain->client)
                    &mdash; <a href="{{ route('admin.clients.show', $domain->client_id) }}" class="text-indigo-600 hover:underline">{{ $domain->client->full_name }}</a>
                @endif
            </p>
        </div>
        <div>
            @php
                $sc = match(strtolower($domain->status)) {
                    'active' => 'bg-emerald-100 text-emerald-700',
                    'pending' => 'bg-amber-100 text-amber-700',
                    'expired' => 'bg-red-100 text-red-700',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $sc }}">{{ ucfirst($domain->status) }}</span>
        </div>
    </div>

    {{-- Info cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Registration Info --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Registration</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Registration Date</dt><dd>{{ $domain->registration_date?->format('d M Y') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Expiry Date</dt>
                    <dd class="{{ $domain->expiry_date?->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $domain->expiry_date?->format('d M Y') ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Next Due Date</dt><dd>{{ $domain->next_due_date?->format('d M Y') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Period</dt><dd>{{ $domain->registration_period }} year(s)</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Type</dt><dd>{{ $domain->type }}</dd></div>
                @if($domain->order_id)
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Order</dt><dd><a href="{{ route('admin.orders.show', $domain->order_id) }}" class="text-indigo-600 hover:underline">#{{ $domain->order_id }}</a></dd></div>
                @endif
            </dl>
        </div>

        {{-- Billing --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Billing</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">First Payment</dt><dd class="font-bold">${{ number_format($domain->first_payment_amount, 2) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Recurring Amount</dt><dd class="font-bold">${{ number_format($domain->recurring_amount, 2) }}/year</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Payment Method</dt><dd>{{ $domain->payment_method ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Premium</dt><dd>{{ $domain->is_premium ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </div>
    </div>

    {{-- Nameservers --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Nameservers</h3>
        @php $ns = is_array($domain->nameservers) ? $domain->nameservers : (json_decode($domain->nameservers ?? '[]', true) ?? []); @endphp
        @if(count($ns) > 0)
        <div class="space-y-2">
            @foreach($ns as $i => $nameserver)
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400 w-10">NS{{ $i + 1 }}</span>
                <span class="font-mono text-sm bg-slate-100 dark:bg-slate-700 px-3 py-1.5 rounded-lg">{{ $nameserver }}</span>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400">No nameservers configured.</p>
        @endif
    </div>

    {{-- Features & Options --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Features</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                <span class="text-sm font-medium">DNS Management</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $domain->dns_management ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">
                    {{ $domain->dns_management ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                <span class="text-sm font-medium">Email Forwarding</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $domain->email_forwarding ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">
                    {{ $domain->email_forwarding ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                <span class="text-sm font-medium">ID Protection</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $domain->id_protection ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">
                    {{ $domain->id_protection ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    @if($domain->notes)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-3">Notes</h3>
        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $domain->notes }}</p>
    </div>
    @endif

</div>
@endsection
