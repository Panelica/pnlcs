@extends('client.layouts.app')
@section('title', $service->product->name ?? 'Service')
@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold">{{ $service->product->name ?? 'Service' }}</h1>
        @if($service->domain)
            <p class="text-slate-500 text-sm mt-1">{{ $service->domain }}</p>
        @endif
    </div>
    <span class="px-3 py-1 rounded-full text-sm font-medium
        {{ $service->status === 'Active' ? 'bg-emerald-100 text-emerald-700' : ($service->status === 'Suspended' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
        {{ ucfirst($service->status) }}
    </span>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Service Details</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Billing Cycle</dt><dd class="font-medium capitalize">{{ $service->billing_cycle ?? 'N/A' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-medium">${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Next Due Date</dt><dd class="font-medium">{{ $service->next_due_date?->format('d M Y') ?? 'N/A' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Registration Date</dt><dd class="font-medium">{{ $service->registration_date?->format('d M Y') ?? 'N/A' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Payment Method</dt><dd class="font-medium capitalize">{{ $service->payment_method ?? 'N/A' }}</dd></div>
        </dl>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Server Details</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Server</dt><dd class="font-medium">{{ $service->server->name ?? 'N/A' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Username</dt><dd class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">{{ $service->username ?? '-' }}</dd></div>
            @if($service->server?->hostname)
            <div class="flex justify-between"><dt class="text-slate-500">Hostname</dt><dd class="font-medium">{{ $service->server->hostname }}</dd></div>
            @endif
        </dl>
    </div>
</div>
@if($service->disk_limit || $service->bw_limit)
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
    <h3 class="font-semibold mb-4">Resource Usage</h3>
    <div class="space-y-4">
        @if($service->disk_limit)
        @php $diskPct = $service->disk_limit > 0 ? min(100, round(($service->disk_usage / $service->disk_limit) * 100)) : 0; $diskColor = $diskPct >= 90 ? 'bg-red-500' : ($diskPct >= 75 ? 'bg-amber-500' : 'bg-indigo-500'); @endphp
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span class="text-slate-600 dark:text-slate-400">Disk Usage</span>
                <span class="font-medium">{{ number_format($service->disk_usage) }} / {{ number_format($service->disk_limit) }} MB ({{ $diskPct }}%)</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                <div class="{{ $diskColor }} h-2 rounded-full" style="width: {{ $diskPct }}%"></div>
            </div>
        </div>
        @endif
        @if($service->bw_limit)
        @php $bwPct = $service->bw_limit > 0 ? min(100, round(($service->bw_usage / $service->bw_limit) * 100)) : 0; $bwColor = $bwPct >= 90 ? 'bg-red-500' : ($bwPct >= 75 ? 'bg-amber-500' : 'bg-emerald-500'); @endphp
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span class="text-slate-600 dark:text-slate-400">Bandwidth Usage</span>
                <span class="font-medium">{{ number_format($service->bw_usage) }} / {{ number_format($service->bw_limit) }} MB ({{ $bwPct }}%)</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                <div class="{{ $bwColor }} h-2 rounded-full" style="width: {{ $bwPct }}%"></div>
            </div>
        </div>
        @endif
    </div>
</div>
@endif
@if(in_array($service->status, ['Active', 'active']))
<div class="flex flex-wrap gap-3 mb-6">
    <a href="{{ route('client.services.upgrade', $service) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        Upgrade / Downgrade
    </a>
    <a href="{{ route('client.services.cancel', $service) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 text-red-600 text-sm font-medium rounded-lg border border-red-300 hover:bg-red-50 transition-colors">
        Request Cancellation
    </a>
</div>
@endif
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <h3 class="font-semibold mb-4">Add-ons</h3>
    @if($service->addons->count())
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2 text-left">Billing Cycle</th>
                    <th class="px-4 py-2 text-left">Next Due</th>
                    <th class="px-4 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($service->addons as $addon)
                <tr>
                    <td class="px-4 py-2">Addon #{{ $addon->addon_id ?? $addon->id }}</td>
                    <td class="px-4 py-2 text-right">${{ number_format($addon->amount, 2) }}</td>
                    <td class="px-4 py-2 capitalize">{{ $addon->billing_cycle }}</td>
                    <td class="px-4 py-2">{{ $addon->next_due_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 text-xs rounded-full {{ $addon->status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($addon->status) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-sm text-slate-400">No add-ons on this service.</p>
    @endif
</div>
<div class="mt-4">
    <a href="{{ route('client.services.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Back to My Services</a>
</div>
@endsection
