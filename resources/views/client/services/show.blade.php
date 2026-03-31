@extends("client.layouts.app")
@section("title", $service->product->name ?? "Service")
@section("content")
<h1 class="text-2xl font-bold mb-6">{{ $service->product->name ?? "Service" }}</h1>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Service Details</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Domain</dt><dd>{{ $service->domain ?? "-" }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd>{{ ucfirst($service->status) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Billing</dt><dd>${{ number_format($service->amount,2) }}/{{ $service->billing_cycle }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Next Due</dt><dd>{{ $service->next_due_date?->format("d M Y") }}</dd></div>
        </dl>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Server Details</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Server</dt><dd>{{ $service->server->name ?? "N/A" }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Username</dt><dd class="font-mono">{{ $service->username ?? "-" }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Registration</dt><dd>{{ $service->registration_date?->format("d M Y") }}</dd></div>
        </dl>
    </div>
</div>
@endsection
