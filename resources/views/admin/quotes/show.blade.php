@extends("admin.layouts.app")
@section("title", "Quote #" . $quote->id)
@section("content")
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold">Quote #{{ $quote->id }}</h1>
        <p class="text-slate-500 text-sm">{{ $quote->client->full_name??'N/A' }} &bull; {{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</p>
    </div>
    <a href="{{ route('admin.quotes.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back to Quotes</a>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Line Items</h2>
                @php $colors=['Draft'=>'slate','Sent'=>'blue','Accepted'=>'emerald','Declined'=>'red']; $c=$colors[$quote->status]??'slate'; @endphp
                <span class="px-3 py-1 text-sm font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">{{ $quote->status }}</span>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="pb-2 text-left font-medium text-slate-600">Description</th>
                        <th class="pb-2 text-right font-medium text-slate-600">Qty</th>
                        <th class="pb-2 text-right font-medium text-slate-600">Unit Price</th>
                        <th class="pb-2 text-right font-medium text-slate-600">Discount</th>
                        <th class="pb-2 text-right font-medium text-slate-600">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quote->items as $item)
                    <tr class="border-b border-slate-100 dark:border-slate-700/50">
                        <td class="py-3">{{ $item->description }}</td>
                        <td class="py-3 text-right">{{ $item->quantity }}</td>
                        <td class="py-3 text-right">${{ number_format($item->unit_price,2) }}</td>
                        <td class="py-3 text-right">{{ $item->discount>0?'$'.number_format($item->discount,2):'-' }}</td>
                        <td class="py-3 text-right font-medium">${{ number_format(max(0,($item->quantity*$item->unit_price)-$item->discount),2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="py-4 text-center text-slate-400">No line items.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-slate-300 dark:border-slate-600">
                    <tr>
                        <td colspan="4" class="pt-3 text-right font-semibold">Subtotal</td>
                        <td class="pt-3 text-right font-semibold">${{ number_format($quote->subtotal,2) }}</td>
                    </tr>
                    @if($quote->tax>0)
                    <tr>
                        <td colspan="4" class="pt-1 text-right">Tax</td>
                        <td class="pt-1 text-right">${{ number_format($quote->tax,2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="4" class="pt-2 text-right font-bold text-base">Total</td>
                        <td class="pt-2 text-right font-bold text-base">${{ number_format($quote->total,2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($quote->proposal)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-3">Proposal</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $quote->proposal }}</div>
        </div>
        @endif
        @if($quote->notes || $quote->customer_notes)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-3">Notes</h2>
            @if($quote->notes)
            <div class="mb-3">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Admin Notes</p>
                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $quote->notes }}</p>
            </div>
            @endif
            @if($quote->customer_notes)
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Customer Notes</p>
                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $quote->customer_notes }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>
    <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-3">Quote Info</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Client</dt><dd class="font-medium">{{ $quote->client->full_name??'N/A' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Valid Until</dt><dd>{{ \Carbon\Carbon::parse($quote->valid_until)->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Items</dt><dd>{{ $quote->items->count() }}</dd></div>
            </dl>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-2">
            <h2 class="font-semibold mb-3">Actions</h2>
            <a href="{{ route('admin.quotes.edit', $quote) }}" class="flex w-full items-center justify-center px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors">Edit Quote</a>
            @if(in_array($quote->status, ['Draft']))
            <form method="POST" action="{{ route('admin.quotes.send', $quote) }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">Send to Client</button>
            </form>
            @endif
            @if(in_array($quote->status, ['Sent']))
            <form method="POST" action="{{ route('admin.quotes.accept', $quote) }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">Accept Quote</button>
            </form>
            <form method="POST" action="{{ route('admin.quotes.decline', $quote) }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">Decline Quote</button>
            </form>
            @endif
            @if(in_array($quote->status, ['Accepted']))
            <form method="POST" action="{{ route('admin.quotes.convert', $quote) }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Convert to Invoice</button>
            </form>
            @endif
            <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" onsubmit="return confirm('Delete this quote?')">
                @csrf @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg transition-colors">Delete Quote</button>
            </form>
        </div>
    </div>
</div>
@endsection
