@extends("admin.layouts.app")
@section("title", "Order #" . $order->order_num)
@section("content")
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Order #{{ $order->order_num }}</h1>
            <p class="text-slate-500">{{ $order->client->full_name ?? "N/A" }} | {{ $order->date?->format("d M Y") }}</p>
        </div>
        <span class="px-3 py-1 text-sm font-medium rounded-full {{ $order->status == "active" ? "bg-emerald-100 text-emerald-700" : ($order->status == "pending" ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($order->status) }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Order Details</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Order Number</dt><dd class="font-mono">{{ $order->order_num }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ $order->date?->format("d M Y") }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-bold">${{ number_format($order->amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Payment Method</dt><dd>{{ $order->payment_method ?? "N/A" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Promo Code</dt><dd>{{ $order->promo_code ?? "None" }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">IP Address</dt><dd class="font-mono text-xs">{{ $order->ip_address }}</dd></div>
            </dl>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Client</h3>
            @if($order->client)
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd><a href="{{ route("admin.clients.show", $order->client) }}" class="text-indigo-600">{{ $order->client->full_name }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $order->client->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Company</dt><dd>{{ $order->client->company_name ?? "-" }}</dd></div>
            </dl>
            @endif
        </div>
    </div>

    @if($order->services->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="font-semibold mb-4">Services in this Order</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="py-2 text-left">Product</th><th class="py-2 text-left">Domain</th><th class="py-2 text-right">Amount</th><th class="py-2 text-left">Status</th></tr></thead>
            <tbody>@foreach($order->services as $svc)<tr class="border-b border-slate-100"><td class="py-2">{{ $svc->product->name ?? "N/A" }}</td><td class="py-2">{{ $svc->domain ?? "-" }}</td><td class="py-2 text-right">${{ number_format($svc->amount, 2) }}</td><td class="py-2"><span class="px-2 py-0.5 text-xs rounded-full {{ $svc->status == "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($svc->status) }}</span></td></tr>@endforeach</tbody>
        </table>
    </div>
    @endif

    @if($order->domains->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Domains in this Order</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="py-2 text-left">Domain</th><th class="py-2 text-left">Type</th><th class="py-2 text-left">Status</th></tr></thead>
            <tbody>@foreach($order->domains as $dom)<tr class="border-b border-slate-100"><td class="py-2 font-medium">{{ $dom->domain }}</td><td class="py-2 capitalize">{{ $dom->type }}</td><td class="py-2"><span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700">{{ ucfirst($dom->status) }}</span></td></tr>@endforeach</tbody>
        </table>
    </div>
    @endif
</div>
@endsection
