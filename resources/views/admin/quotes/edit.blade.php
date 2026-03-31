@extends("admin.layouts.app")
@section("title", "Edit Quote #" . $quote->id)
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Edit Quote #{{ $quote->id }}</h1>
    <a href="{{ route('admin.quotes.show', $quote) }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back to Quote</a>
</div>
@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<form method="POST" action="{{ route('admin.quotes.update', $quote) }}" x-data="quoteFormEdit({{ json_encode($quote->items->map(fn($i)=>['description'=>$i->description,'quantity'=>(float)$i->quantity,'unit_price'=>(float)$i->unit_price,'discount'=>(float)$i->discount,'taxable'=>(bool)$i->taxable,'amount'=>max(0,($i->quantity*$i->unit_price)-$i->discount)])) }})">
    @csrf @method('PUT')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="font-semibold mb-4">Quote Details</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Subject *</label>
                        <input type="text" name="subject" value="{{ old('subject',$quote->subject) }}" required class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Date *</label>
                        <input type="date" name="date" value="{{ old('date', \Carbon\Carbon::parse($quote->date)->toDateString()) }}" required class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Valid Until *</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until', \Carbon\Carbon::parse($quote->valid_until)->toDateString()) }}" required class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold">Line Items</h2>
                    <button type="button" @click="addItem()" class="px-3 py-1.5 text-xs font-medium bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition-colors">+ Add Item</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="pb-2 text-left font-medium text-slate-600 w-1/2">Description</th>
                                <th class="pb-2 text-left font-medium text-slate-600 w-20">Qty</th>
                                <th class="pb-2 text-left font-medium text-slate-600 w-24">Unit Price</th>
                                <th class="pb-2 text-left font-medium text-slate-600 w-20">Discount</th>
                                <th class="pb-2 text-center font-medium text-slate-600 w-16">Taxable</th>
                                <th class="pb-2 text-right font-medium text-slate-600 w-24">Amount</th>
                                <th class="pb-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b border-slate-100 dark:border-slate-700/50">
                                    <td class="py-2 pr-2"><input type="text" :name="`items[${index}][description]`" x-model="item.description" required class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 focus:ring-1 focus:ring-indigo-500 focus:outline-none"></td>
                                    <td class="py-2 pr-2"><input type="number" :name="`items[${index}][quantity]`" x-model.number="item.quantity" @input="calcItem(item)" min="0.01" step="0.01" class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 focus:ring-1 focus:ring-indigo-500 focus:outline-none"></td>
                                    <td class="py-2 pr-2"><input type="number" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" @input="calcItem(item)" min="0" step="0.01" class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 focus:ring-1 focus:ring-indigo-500 focus:outline-none"></td>
                                    <td class="py-2 pr-2"><input type="number" :name="`items[${index}][discount]`" x-model.number="item.discount" @input="calcItem(item)" min="0" step="0.01" class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 focus:ring-1 focus:ring-indigo-500 focus:outline-none"></td>
                                    <td class="py-2 pr-2 text-center"><input type="hidden" :name="`items[${index}][taxable]`" value="0"><input type="checkbox" :name="`items[${index}][taxable]`" x-model="item.taxable" value="1" class="rounded border-slate-300 text-indigo-600"></td>
                                    <td class="py-2 pr-2 text-right font-medium" x-text="'$' + item.amount.toFixed(2)"></td>
                                    <td class="py-2 text-center"><button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600 text-xs">✕</button></td>
                                </tr>
                            </template>
                            <tr x-show="items.length===0"><td colspan="7" class="py-4 text-center text-slate-400 text-sm">No items. Click "+ Add Item".</td></tr>
                        </tbody>
                        <tfoot class="border-t-2 border-slate-300 dark:border-slate-600">
                            <tr><td colspan="5" class="pt-3 text-right font-semibold text-sm">Subtotal</td><td class="pt-3 text-right font-semibold" x-text="'$' + subtotal().toFixed(2)"></td><td></td></tr>
                            <tr><td colspan="5" class="pt-1 text-right font-bold text-base">Total</td><td class="pt-1 text-right font-bold text-base" x-text="'$' + subtotal().toFixed(2)"></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="font-semibold mb-4">Notes</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Admin Notes</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('notes',$quote->notes) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Customer Notes</label>
                        <textarea name="customer_notes" rows="3" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('customer_notes',$quote->customer_notes) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Proposal</label>
                        <textarea name="proposal" rows="4" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('proposal',$quote->proposal) }}</textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">Update Quote</button>
        </div>
    </div>
</form>
@push('scripts')
<script>
function quoteFormEdit(existingItems) {
    return {
        items: existingItems.map(i => ({...i, amount: Math.max(0,(i.quantity*i.unit_price)-i.discount)})),
        addItem() { this.items.push({description:'',quantity:1,unit_price:0,discount:0,taxable:true,amount:0}); },
        removeItem(index) { this.items.splice(index,1); },
        calcItem(item) { item.amount = Math.max(0,(item.quantity*item.unit_price)-item.discount); },
        subtotal() { return this.items.reduce((s,i)=>s+i.amount,0); }
    };
}
</script>
@endpush
@endsection
