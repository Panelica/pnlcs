@extends('admin.layouts.app')
@section('title', __('admin.quotes.edit_quote') . ' #' . $quote->id)
@section('content')
<div class="page-header">
    <h1>{{ __('admin.quotes.edit_quote') }} #{{ $quote->id }}</h1>
    <a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<form method="POST" action="{{ route('admin.quotes.update', $quote) }}" x-data="quoteFormEdit({{ json_encode($quote->items->map(fn($i)=>['description'=>$i->description,'quantity'=>(float)$i->quantity,'unit_price'=>(float)$i->unit_price,'discount'=>(float)$i->discount,'taxable'=>(bool)$i->taxable,'amount'=>max(0,($i->quantity*$i->unit_price)-$i->discount)])) }})">
    @csrf @method('PUT')
    <div style="display:grid;grid-template-columns:1fr 280px;gap:15px;">
        <div>
            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.quotes.quote_details') }}</strong></div>
                <div class="card-body">
                    <div class="form-group"><label class="form-label">{{ __('common.form.subject') }}<span style="color:#d9534f;">*</span></label><input type="text" name="subject" value="{{ old('subject',$quote->subject) }}" required class="form-control"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                        <div class="form-group"><label class="form-label">{{ __('common.form.date') }}</label><input type="date" name="date" value="{{ old('date', \Carbon\Carbon::parse($quote->date)->toDateString()) }}" required class="form-control"></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.quotes.valid_until') }}</label><input type="date" name="valid_until" value="{{ old('valid_until', \Carbon\Carbon::parse($quote->valid_until)->toDateString()) }}" required class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <strong>Line Items</strong>
                    <button type="button" @click="addItem()" class="btn btn-default btn-xs">+ Add Item</button>
                </div>
                <div class="card-body" style="padding:0;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead><tr style="border-bottom:1px solid #ddd;background:#f9f9f9;">
                            <th style="padding:6px 10px;text-align:left;font-weight:600;color:#555;">{{ __('common.table.description') }}</th>
                            <th style="padding:6px 8px;text-align:center;width:60px;font-weight:600;color:#555;">{{ __('admin.quotes.qty') }}</th>
                            <th style="padding:6px 8px;text-align:right;width:90px;font-weight:600;color:#555;">{{ __('admin.quotes.unit_price') }}</th>
                            <th style="padding:6px 8px;text-align:right;width:80px;font-weight:600;color:#555;">{{ __('common.table.discount') }}</th>
                            <th style="padding:6px 8px;text-align:center;width:60px;font-weight:600;color:#555;">{{ __('admin.quotes.taxable') }}</th>
                            <th style="padding:6px 8px;text-align:right;width:80px;font-weight:600;color:#555;">{{ __('common.table.amount') }}</th>
                            <th style="width:30px;"></th>
                        </tr></thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr style="border-bottom:1px solid #f5f5f5;">
                                    <td style="padding:6px 10px;"><input type="text" :name="`items[${index}][description]`" x-model="item.description" required class="form-control" style="font-size:13px;"></td>
                                    <td style="padding:6px 8px;"><input type="number" :name="`items[${index}][quantity]`" x-model.number="item.quantity" @input="calcItem(item)" min="0.01" step="0.01" class="form-control" style="font-size:13px;text-align:center;"></td>
                                    <td style="padding:6px 8px;"><input type="number" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" @input="calcItem(item)" min="0" step="0.01" class="form-control" style="font-size:13px;text-align:right;"></td>
                                    <td style="padding:6px 8px;"><input type="number" :name="`items[${index}][discount]`" x-model.number="item.discount" @input="calcItem(item)" min="0" step="0.01" class="form-control" style="font-size:13px;text-align:right;"></td>
                                    <td style="padding:6px 8px;text-align:center;"><input type="hidden" :name="`items[${index}][taxable]`" value="0"><input type="checkbox" :name="`items[${index}][taxable]`" x-model="item.taxable" value="1"></td>
                                    <td style="padding:6px 8px;text-align:right;font-weight:600;font-family:monospace;" x-text="'$' + item.amount.toFixed(2)"></td>
                                    <td style="text-align:center;"><button type="button" @click="removeItem(index)" style="background:none;border:none;color:#d9534f;cursor:pointer;font-size:16px;">&times;</button></td>
                                </tr>
                            </template>
                            <tr x-show="items.length===0"><td colspan="7" style="text-align:center;color:#999;padding:20px;font-size:13px;">No items.</td></tr>
                        </tbody>
                        <tfoot style="border-top:2px solid #aaa;">
                            <tr><td colspan="5" style="padding:8px 10px;text-align:right;font-weight:600;">Subtotal</td><td style="padding:8px;text-align:right;font-weight:600;font-family:monospace;" x-text="'$' + subtotal().toFixed(2)"></td><td></td></tr>
                            <tr style="background:#f5f5f5;"><td colspan="5" style="padding:8px 10px;text-align:right;font-weight:700;font-size:14px;">Total</td><td style="padding:8px;text-align:right;font-weight:700;font-size:14px;font-family:monospace;" x-text="'$' + subtotal().toFixed(2)"></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div>
            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.quotes.notes') }}</strong></div>
                <div class="card-body">
                    <div class="form-group"><label class="form-label">{{ __('admin.quotes.admin_notes') }}</label><textarea name="notes" rows="3" class="form-control">{{ old('notes',$quote->notes) }}</textarea></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.quotes.customer_notes') }}</label><textarea name="customer_notes" rows="3" class="form-control">{{ old('customer_notes',$quote->customer_notes) }}</textarea></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.quotes.proposal') }}</label><textarea name="proposal" rows="4" class="form-control">{{ old('proposal',$quote->proposal) }}</textarea></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;">{{ __('admin.quotes.update_quote') }}</button>
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
