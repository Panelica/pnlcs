@extends("admin.layouts.app")
@section("title", "Invoice #" . $invoice->invoice_num)
@section("content")
<div class="max-w-4xl">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Invoice #{{ $invoice->invoice_num }}</h1>
            <p class="text-slate-500 text-sm mt-1">
                <a href="{{ route('admin.clients.show', $invoice->client) }}" class="text-indigo-600 hover:underline">
                    {{ $invoice->client->display_name ?? 'N/A' }}
                </a>
                &nbsp;·&nbsp; {{ $invoice->date?->format('d M Y') }}
                &nbsp;·&nbsp; Due {{ $invoice->due_date?->format('d M Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 text-sm font-medium rounded-full
                {{ $invoice->status === 'Paid'      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                  ($invoice->status === 'Unpaid'    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                  ($invoice->status === 'Overdue'   ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                   'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300')) }}">
                {{ ucfirst($invoice->status) }}
            </span>
            <a href="{{ route('admin.invoices.index') }}" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
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

        {{-- Left: items + transactions --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Line Items --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Line Items</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 text-left text-slate-500 font-medium">Description</th>
                            <th class="py-2 text-center text-slate-500 font-medium w-16">Taxed</th>
                            <th class="py-2 text-right text-slate-500 font-medium w-28">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                        <tr class="border-b border-slate-100 dark:border-slate-700/50">
                            <td class="py-3">
                                <span class="text-xs text-slate-400 mr-1 uppercase">{{ $item->type }}</span>
                                {{ $item->description }}
                            </td>
                            <td class="py-3 text-center">
                                @if($item->taxed)
                                    <span class="text-emerald-500">✓</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-right font-mono">${{ number_format($item->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-400 text-sm">No line items</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 dark:border-slate-700">
                            <td class="py-3 text-slate-500 font-medium" colspan="2">Subtotal</td>
                            <td class="py-3 text-right font-mono font-semibold">${{ number_format($invoice->subtotal, 2) }}</td>
                        </tr>
                        @if($invoice->tax > 0)
                        <tr>
                            <td class="py-1.5 text-slate-500 text-sm" colspan="2">
                                Tax @if($invoice->tax_rate > 0)({{ $invoice->tax_rate }}%)@endif
                            </td>
                            <td class="py-1.5 text-right font-mono text-sm">${{ number_format($invoice->tax, 2) }}</td>
                        </tr>
                        @endif
                        @if($invoice->tax2 > 0)
                        <tr>
                            <td class="py-1.5 text-slate-500 text-sm" colspan="2">
                                Tax 2 @if($invoice->tax_rate2 > 0)({{ $invoice->tax_rate2 }}%)@endif
                            </td>
                            <td class="py-1.5 text-right font-mono text-sm">${{ number_format($invoice->tax2, 2) }}</td>
                        </tr>
                        @endif
                        @if($invoice->credit > 0)
                        <tr>
                            <td class="py-1.5 text-emerald-600 text-sm" colspan="2">Credit Applied</td>
                            <td class="py-1.5 text-right font-mono text-sm text-emerald-600">-${{ number_format($invoice->credit, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="border-t-2 border-slate-300 dark:border-slate-600">
                            <td class="py-3 font-bold text-base" colspan="2">Total</td>
                            <td class="py-3 text-right font-bold text-base font-mono">${{ number_format($invoice->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Transactions / Payment History --}}
            @if($invoice->transactions->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Payment History</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 text-left text-slate-500 font-medium">Date</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Gateway</th>
                            <th class="py-2 text-left text-slate-500 font-medium">Transaction ID</th>
                            <th class="py-2 text-right text-slate-500 font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->transactions as $tx)
                        <tr class="border-b border-slate-100 dark:border-slate-700/50">
                            <td class="py-2.5">{{ $tx->date?->format('d M Y') }}</td>
                            <td class="py-2.5 capitalize">{{ $tx->gateway }}</td>
                            <td class="py-2.5 font-mono text-xs text-slate-500">{{ $tx->transaction_id ?? '—' }}</td>
                            <td class="py-2.5 text-right font-mono text-emerald-600">+${{ number_format($tx->amount_in, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Notes --}}
            @if($invoice->notes)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-2">Notes</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{{ $invoice->notes }}</p>
            </div>
            @endif

        </div>

        {{-- Right: actions + client info --}}
        <div class="space-y-6">

            {{-- Actions --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Actions</h3>
                <div class="space-y-3">

                    {{-- Mark Paid --}}
                    @if(in_array($invoice->status, ['Unpaid', 'Overdue']))
                    <div x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Mark as Paid
                        </button>
                        <div x-show="open" x-transition class="mt-3 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg" style="display:none;">
                            <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium mb-1">Transaction ID (optional)</label>
                                        <input type="text" name="transaction_id" placeholder="e.g. ch_abc123"
                                               class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium mb-1">Gateway</label>
                                        <select name="gateway"
                                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <option value="manual">Manual</option>
                                            <option value="banktransfer">Bank Transfer</option>
                                            <option value="paypal">PayPal</option>
                                            <option value="stripe">Stripe</option>
                                            <option value="credit">Credit</option>
                                        </select>
                                    </div>
                                    <button type="submit"
                                            class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        Confirm Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif

                    {{-- Cancel --}}
                    @if($invoice->status !== 'Paid' && $invoice->status !== 'Cancelled')
                    <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}"
                          onsubmit="return confirm('Cancel this invoice? This cannot be undone.')">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Cancel Invoice
                        </button>
                    </form>
                    @endif

                    @if($invoice->status === 'Paid')
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-center">
                        <p class="text-sm text-emerald-700 dark:text-emerald-400 font-medium">✓ Paid on {{ $invoice->date_paid?->format('d M Y H:i') }}</p>
                    </div>
                    @endif

                    @if($invoice->status === 'Cancelled')
                    <div class="p-3 bg-slate-100 dark:bg-slate-700/50 rounded-lg text-center">
                        <p class="text-sm text-slate-500 font-medium">Invoice Cancelled</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Client info --}}
            @if($invoice->client)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Client</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Name</dt>
                        <dd><a href="{{ route('admin.clients.show', $invoice->client) }}" class="text-indigo-600 hover:underline font-medium">{{ $invoice->client->display_name }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Email</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ $invoice->client->email }}</dd>
                    </div>
                    @if($invoice->client->address1)
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Address</dt>
                        <dd class="text-slate-700 dark:text-slate-300">
                            {{ $invoice->client->address1 }}<br>
                            {{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->postcode }}<br>
                            {{ $invoice->client->country }}
                        </dd>
                    </div>
                    @endif
                    @if($invoice->client->tax_id)
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider mb-0.5">Tax ID</dt>
                        <dd class="font-mono text-xs">{{ $invoice->client->tax_id }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

            {{-- Invoice meta --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="font-semibold mb-4">Invoice Details</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Invoice #</dt>
                        <dd class="font-mono font-medium">{{ $invoice->invoice_num }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Date</dt>
                        <dd>{{ $invoice->date?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Due Date</dt>
                        <dd class="{{ $invoice->due_date?->isPast() && $invoice->status !== 'Paid' ? 'text-red-600 font-medium' : '' }}">
                            {{ $invoice->due_date?->format('d M Y') }}
                        </dd>
                    </div>
                    @if($invoice->payment_method)
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Payment Method</dt>
                        <dd class="capitalize">{{ $invoice->payment_method }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

        </div>
    </div>
</div>
@endsection
