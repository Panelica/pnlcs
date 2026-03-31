@extends("admin.layouts.app")
@section("title", "Service #" . $service->id)
@section("content")
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Service #{{ $service->id }}</h1>
            <p class="text-slate-500">{{ $service->product->name ?? "N/A" }} — {{ $service->domain ?? "No domain" }}</p>
        </div>
        <span class="px-3 py-1 text-sm font-medium rounded-full {{ $service->status == "active" ? "bg-emerald-100 text-emerald-700" : ($service->status == "suspended" ? "bg-red-100 text-red-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($service->status) }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Service Information</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Product</dt><dd>{{ $service->product->name ?? "N/A" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Domain</dt><dd>{{ $service->domain ?? "-" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Server</dt><dd>{{ $service->server->name ?? "None" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Username</dt><dd class="font-mono">{{ $service->username ?? "-" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Registration Date</dt><dd>{{ $service->registration_date?->format("d M Y") ?? "-" }}</dd></div>
            </dl>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Billing</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-bold text-lg">${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">First Payment</dt><dd>${{ number_format($service->first_payment_amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Next Due Date</dt><dd>{{ $service->next_due_date?->format("d M Y") ?? "-" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Payment Method</dt><dd>{{ $service->payment_method ?? "-" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Client</dt><dd><a href="{{ route("admin.clients.show", $service->client_id) }}" class="text-indigo-600">{{ $service->client->full_name ?? "N/A" }}</a></dd></div>
            </dl>
        </div>
    </div>

    @if($service->disk_limit > 0 || $service->bw_limit > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="font-semibold mb-4">Resource Usage</h3>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-slate-500 mb-1">Disk Usage</p>
                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                    @php $diskPct = $service->disk_limit > 0 ? min(100, ($service->disk_usage / $service->disk_limit) * 100) : 0; @endphp
                    <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ $diskPct }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ number_format($service->disk_usage / 1024 / 1024, 1) }}MB / {{ number_format($service->disk_limit / 1024 / 1024, 1) }}MB</p>
            </div>
            <div>
                <p class="text-sm text-slate-500 mb-1">Bandwidth Usage</p>
                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3">
                    @php $bwPct = $service->bw_limit > 0 ? min(100, ($service->bw_usage / $service->bw_limit) * 100) : 0; @endphp
                    <div class="bg-emerald-600 h-3 rounded-full" style="width: {{ $bwPct }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ number_format($service->bw_usage / 1024 / 1024, 1) }}MB / {{ number_format($service->bw_limit / 1024 / 1024, 1) }}MB</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Module Actions -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Module Actions</h3>
        <div class="flex flex-wrap gap-2">
            <button disabled class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg opacity-50 cursor-not-allowed">Create Account</button>
            <button disabled class="px-4 py-2 bg-amber-600 text-white text-sm rounded-lg opacity-50 cursor-not-allowed">Suspend</button>
            <button disabled class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg opacity-50 cursor-not-allowed">Unsuspend</button>
            <button disabled class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg opacity-50 cursor-not-allowed">Terminate</button>
            <button disabled class="px-4 py-2 bg-slate-600 text-white text-sm rounded-lg opacity-50 cursor-not-allowed">Change Password</button>
            <p class="w-full text-xs text-slate-400 mt-2">Module actions require a server module to be configured for this product.</p>
        </div>
    </div>
</div>
@endsection
