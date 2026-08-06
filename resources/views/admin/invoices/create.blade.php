@extends('admin.layouts.app')
@section('title', __('admin.invoices.create_invoice'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.invoices.create_invoice') }}</h1>
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.invoices.back_to_invoices') }}</a>
</div>

@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $error)<div>&bull; {{ $error }}</div>@endforeach
</div>
@endif

<div x-data="invoiceBuilder()">
<form method="POST" action="{{ route('admin.invoices.store') }}">
    @csrf

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">

        {{-- Left column --}}
        <div>
            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.client') }}</strong></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.invoices.select_client') }} <span style="color:#d9534f;">*</span></label>
                        <select name="client_id" required class="form-control">
                            <option value="">— Choose a client —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $selectedClient?->id) == $client->id ? 'selected' : '' }}>
                                {{ $client->display_name }} ({{ $client->email }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:15px;">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <strong>{{ __('admin.invoices.line_items') }}</strong>
                    <button type="button" @click="addItem()" class="btn btn-default btn-xs">+ {{ __('admin.invoices.add_item') }}</button>
                </div>
                <div class="card-body">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="border-bottom:1px solid #ddd;">
                                <th style="padding:6px 8px;text-align:left;font-weight:600;color:#555;">{{ __('common.table.description') }}</th>
                                <th style="padding:6px 8px;text-align:center;font-weight:600;color:#555;width:60px;">{{ __('common.table.tax') }}</th>
                                <th style="padding:6px 8px;text-align:right;font-weight:600;color:#555;width:110px;">{{ __('common.table.amount') }}</th>
                                <th style="width:30px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr style="border-bottom:1px solid #f5f5f5;">
                                    <td style="padding:6px 8px;">
                                        <input type="text" :name="`items[${index}][description]`" x-model="item.description"
                                               placeholder="{{ __('admin.invoices.description_placeholder') }}" required class="form-control" style="font-size:13px;">
                                    </td>
                                    <td style="padding:6px 8px;text-align:center;">
                                        <input type="hidden" :name="`items[${index}][taxed]`" value="0">
                                        <input type="checkbox" :name="`items[${index}][taxed]`" :value="1" x-model="item.taxed">
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <input type="number" :name="`items[${index}][amount]`" x-model.number="item.amount"
                                               @input="recalculate()" placeholder="0.00" step="0.01" min="0" required
                                               class="form-control" style="font-size:13px;text-align:right;">
                                    </td>
                                    <td style="padding:6px 4px;text-align:center;">
                                        <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                                style="background:none;border:none;color:#d9534f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>{{ __('admin.invoices.notes') }}</strong></div>
                <div class="card-body">
                    <div class="form-group" style="margin:0;">
                        <textarea name="notes" rows="3" placeholder="{{ __('admin.invoices.notes_placeholder') }}" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div>
            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.dates') }}</strong></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.invoices.invoice_date') }} <span style="color:#d9534f;">*</span></label>
                        <input type="date" name="date" required value="{{ old('date', now()->toDateString()) }}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.invoices.due_date') }} <span style="color:#d9534f;">*</span></label>
                        <input type="date" name="due_date" required value="{{ old('due_date', now()->addDays(14)->toDateString()) }}" class="form-control">
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.payment_method') }}</strong></div>
                <div class="card-body">
                    <div class="form-group" style="margin:0;">
                        <select name="payment_method" class="form-control">
                            <option value="">— None —</option>
                            @foreach($gateways as $gw)
                            <option value="{{ $gw }}" {{ old('payment_method') == $gw ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $gw)) }}</option>
                            @endforeach
                            @unless(in_array('banktransfer', $gateways, true))
                            <option value="banktransfer" {{ old('payment_method') == 'banktransfer' ? 'selected' : '' }}>{{ __('admin.invoices.bank_transfer') }}</option>
                            @endunless
                            <option value="manual" {{ old('payment_method') == 'manual' ? 'selected' : '' }}>{{ __('admin.invoices.manual') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.summary') }}</strong></div>
                <div class="card-body">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.subtotal') }}</td><td style="padding:4px 0;text-align:right;font-family:monospace;" x-text="'$' + subtotal.toFixed(2)">$0.00</td></tr>
                        <tr style="border-top:2px solid #aaa;background:#f5f5f5;"><td style="padding:6px 0;font-weight:700;">{{ __('admin.invoices.est_total') }}</td><td style="padding:6px 0;text-align:right;font-weight:700;font-family:monospace;font-size:15px;" x-text="'$' + subtotal.toFixed(2)">$0.00</td></tr>
                    </table>
                    <p style="font-size:11px;color:#999;margin-top:6px;">{{ __('admin.invoices.taxes_note') }}</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;">{{ __('admin.invoices.create_invoice') }}</button>
        </div>

    </div>
</form>
</div>

<script>
function invoiceBuilder() {
    return {
        items: [{ description: '', amount: 0, taxed: true }],
        subtotal: 0,
        addItem() { this.items.push({ description: '', amount: 0, taxed: true }); },
        removeItem(index) { if (this.items.length > 1) { this.items.splice(index, 1); this.recalculate(); } },
        recalculate() { this.subtotal = this.items.reduce((s, i) => s + (parseFloat(i.amount) || 0), 0); }
    };
}
</script>
@endsection
