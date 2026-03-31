@extends("admin.layouts.app")
@section("title", "Order #" . $order->order_num)
@section("content")
<div class="max-w-4xl">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Order #{{ $order->order_num }}</h1>
            <p class="text-slate-500 text-sm mt-1">
                <a href="{{ route('admin.clients.show', $order->client) }}" class="text-indigo-600 hover:underline">
                    {{ $order->client->display_name ?? 'N/A' }}
                </a>
                &nbsp;·&nbsp; {{ $order->date?->format('d M Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 text-sm font-medium rounded-full
                {{ $order->status === 'Active'    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                  ($order->status === 'Pending'   ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                  ($order->status === 'Fraud'     ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                  ($order->status === 'Cancelled' ? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' :
                   'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'))) }}">
                {{ ucfirst($order->status) }}
            </span>
            <a href="{{ route('admin.orders.index') }}" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                ← Back
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-400">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: services + domains --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Order Details --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Order Details</h3>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Order Number</dt>
                        <dd class="font-mono font-medium">{{ $order->order_num }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Date</dt>
                        <dd>{{ $order->date?->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Amount</dt>
                        <dd class="font-bold text-base">${{ number_format($order->amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Payment Method</dt>
                        <dd class="capitalize">{{ $order->payment_method ?? '—' }}</dd>
                    </div>
                    @if($order->promo_code)
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Promo Code</dt>
                        <dd class="font-mono text-sm bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded inline-block">{{ $order->promo_code }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">IP Address</dt>
                        <dd class="font-mono text-xs text-slate-500">{{ $order->ip_address }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Services --}}
            @if($order->services->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Services ({{ $order->services->count() }})</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 text-left text-slate-500 font-medium">Product</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Domain</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Billing</th>
                            <th class="py-2 text-right text-slate-500 font-medium">Amount</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->services as $svc)
                        <tr class="border-b border-slate-100 dark:border-slate-700/50">
                            <td class="py-2.5">
                                <a href="{{ route('admin.services.show', $svc) }}" class="text-indigo-600 hover:underline">
                                    {{ $svc->product->name ?? 'N/A' }}
                                </a>
                            </td>
                            <td class="py-2.5 text-slate-600 dark:text-slate-400 text-xs font-mono">{{ $svc->domain ?? '—' }}</td>
                            <td class="py-2.5 text-slate-600 dark:text-slate-400">{{ $svc->billing_cycle }}</td>
                            <td class="py-2.5 text-right font-mono">${{ number_format($svc->amount, 2) }}</td>
                            <td class="py-2.5">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full
                                    {{ $svc->status === 'Active'    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                      ($svc->status === 'Pending'   ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                      ($svc->status === 'Suspended' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' :
                                       'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300')) }}">
                                    {{ ucfirst($svc->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Domains --}}
            @if($order->domains->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Domains ({{ $order->domains->count() }})</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 text-left text-slate-500 font-medium">Domain</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Type</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Expiry</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->domains as $dom)
                        <tr class="border-b border-slate-100 dark:border-slate-700/50">
                            <td class="py-2.5 font-medium font-mono text-sm">{{ $dom->domain }}</td>
                            <td class="py-2.5 capitalize text-slate-600 dark:text-slate-400">{{ $dom->type }}</td>
                            <td class="py-2.5 text-slate-600 dark:text-slate-400">{{ $dom->expiry_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2.5">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                    {{ ucfirst($dom->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Fraud output --}}
            @if($order->status === 'Fraud' && $order->fraud_output)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6">
                <h3 class="font-semibold text-red-700 dark:text-red-400 mb-2">Fraud Information</h3>
                <p class="text-sm text-red-600 dark:text-red-400">{{ $order->fraud_output }}</p>
            </div>
            @endif

            {{-- Notes --}}
            @if($order->notes)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-2">Notes</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{{ $order->notes }}</p>
            </div>
            @endif

        </div>

        {{-- Right: actions + client info --}}
        <div class="space-y-6">

            {{-- Actions --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Actions</h3>
                <div class="space-y-3">

                    {{-- Accept --}}
                    @if($order->status === 'Pending')
                    <form method="POST" action="{{ route('admin.orders.accept', $order) }}">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Accept Order
                        </button>
                    </form>
                    @endif

                    {{-- Cancel --}}
                    @if(!in_array($order->status, ['Cancelled', 'Fraud']))
                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}"
                          onsubmit="return confirm('Cancel this order and terminate its services?')">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                            Cancel Order
                        </button>
                    </form>
                    @endif

                    {{-- Mark Fraud --}}
                    @if($order->status !== 'Fraud')
                    <form method="POST" action="{{ route('admin.orders.fraud', $order) }}"
                          onsubmit="return confirm('Mark this order as fraud? Services will be suspended.')">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Mark as Fraud
                        </button>
                    </form>
                    @endif

                    {{-- Delete --}}
                    @if(in_array($order->status, ['Cancelled', 'Fraud', 'Pending']))
                    <form method="POST" action="{{ route('admin.orders.delete', $order) }}"
                          onsubmit="return confirm('Permanently delete order #{{ $order->order_num }}? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full px-4 py-2 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium rounded-lg transition-colors">
                            Delete Order
                        </button>
                    </form>
                    @endif

                    @if($order->invoice)
                    <a href="{{ route('admin.invoices.show', $order->invoice) }}"
                       class="flex items-center justify-center gap-2 w-full px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        View Invoice #{{ $order->invoice->invoice_num }}
                    </a>
                    @endif
                </div>
            </div>

            {{-- Client info --}}
            @if($order->client)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Client</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Name</dt>
                        <dd><a href="{{ route('admin.clients.show', $order->client) }}" class="text-indigo-600 hover:underline font-medium">{{ $order->client->display_name }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Email</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ $order->client->email }}</dd>
                    </div>
                    @if($order->client->company_name)
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Company</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ $order->client->company_name }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
