@extends('admin.layouts.app')
@section('title', 'Service #' . $service->id)
@section('content')
<div class="max-w-5xl space-y-6">

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

    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ route('admin.services.index') }}" class="text-slate-400 hover:text-slate-600 text-sm">&larr; Services</a>
            </div>
            <h1 class="text-2xl font-bold">Service #{{ $service->id }}</h1>
            <p class="text-slate-500 mt-1">
                {{ $service->product->name ?? 'No Product' }}
                @if($service->domain) &mdash; <span class="font-mono text-sm">{{ $service->domain }}</span> @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $statusColors = [
                    'active'     => 'bg-emerald-100 text-emerald-700',
                    'pending'    => 'bg-amber-100 text-amber-700',
                    'suspended'  => 'bg-red-100 text-red-700',
                    'terminated' => 'bg-slate-100 text-slate-600',
                    'cancelled'  => 'bg-slate-100 text-slate-600',
                ];
                $sc = $statusColors[strtolower($service->status)] ?? 'bg-slate-100 text-slate-700';
            @endphp
            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $sc }}">{{ ucfirst($service->status) }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Service Info</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Product</dt><dd class="font-medium text-right">{{ $service->product->name ?? 'N/A' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Domain</dt><dd class="font-mono text-right truncate">{{ $service->domain ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Username</dt><dd class="font-mono text-right">{{ $service->username ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Client</dt><dd class="text-right"><a href="{{ route('admin.clients.show', $service->client_id) }}" class="text-indigo-600 hover:underline">{{ $service->client->full_name ?? 'N/A' }}</a></dd></div>
                @if($service->order_id)
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Order</dt><dd><a href="{{ route('admin.orders.show', $service->order_id) }}" class="text-indigo-600 hover:underline">#{{ $service->order_id }}</a></dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Server &amp; Module</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Server</dt><dd class="font-medium text-right">{{ $service->server->name ?? 'None assigned' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Module</dt><dd class="font-medium text-right">{{ $service->product->server_type ?? 'None' }}</dd></div>
                @if($service->suspension_date)
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Suspended</dt><dd class="text-right text-red-600">{{ $service->suspension_date->format('d M Y') }}</dd></div>
                @endif
                @if($service->suspension_reason)
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Reason</dt><dd class="text-right text-slate-600 truncate">{{ $service->suspension_reason }}</dd></div>
                @endif
                @if($service->termination_date)
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Terminated</dt><dd class="text-right text-red-600">{{ $service->termination_date->format('d M Y') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Billing</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Amount</dt><dd class="font-bold text-lg">${{ number_format($service->amount, 2) }}<span class="text-xs font-normal text-slate-400">/{{ $service->billing_cycle }}</span></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">First Payment</dt><dd>${{ number_format($service->first_payment_amount, 2) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Registered</dt><dd>{{ $service->registration_date?->format('d M Y') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Next Due</dt><dd class="{{ $service->next_due_date?->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $service->next_due_date?->format('d M Y') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500 shrink-0">Payment</dt><dd>{{ $service->payment_method ?? '-' }}</dd></div>
            </dl>
        </div>
    </div>

    @if($service->disk_limit > 0 || $service->bw_limit > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Resource Usage</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @if($service->disk_limit > 0)
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="text-slate-500">Disk Usage</span>
                    <span class="font-medium">{{ number_format($service->disk_usage / 1024 / 1024, 1) }} MB / {{ number_format($service->disk_limit / 1024 / 1024, 1) }} MB</span>
                </div>
                @php $diskPct = min(100, ($service->disk_limit > 0 ? ($service->disk_usage / $service->disk_limit) * 100 : 0)); @endphp
                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                    <div class="h-2.5 rounded-full {{ $diskPct > 85 ? 'bg-red-500' : 'bg-indigo-600' }}" style="width: {{ $diskPct }}%"></div>
                </div>
            </div>
            @endif
            @if($service->bw_limit > 0)
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="text-slate-500">Bandwidth</span>
                    <span class="font-medium">{{ number_format($service->bw_usage / 1024 / 1024, 1) }} MB / {{ number_format($service->bw_limit / 1024 / 1024, 1) }} MB</span>
                </div>
                @php $bwPct = min(100, ($service->bw_limit > 0 ? ($service->bw_usage / $service->bw_limit) * 100 : 0)); @endphp
                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                    <div class="h-2.5 rounded-full {{ $bwPct > 85 ? 'bg-red-500' : 'bg-emerald-600' }}" style="width: {{ $bwPct }}%"></div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-4">Module Actions</h3>
        @if(!$service->product?->server_type)
            <p class="text-sm text-slate-400">No server module configured for this product.</p>
        @else
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'create']) }}" onsubmit="return confirm('Create account on server?')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">Create Account</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'suspend']) }}" onsubmit="return confirm('Suspend this service?')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition-colors">Suspend</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'unsuspend']) }}" onsubmit="return confirm('Unsuspend this service?')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">Unsuspend</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'terminate']) }}" onsubmit="return confirm('TERMINATE this service? This cannot be undone.')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">Terminate</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'changepassword']) }}" class="flex items-center gap-2">
                @csrf
                <input type="password" name="password" placeholder="New password" required minlength="6"
                       class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">Change Password</button>
            </form>
        </div>
        <p class="text-xs text-slate-400 mt-3">Module: <span class="font-mono">{{ $service->product->server_type }}</span></p>
        @endif
    </div>

    @if($service->notes)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-slate-400 mb-3">Notes</h3>
        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $service->notes }}</p>
    </div>
    @endif

</div>
@endsection
