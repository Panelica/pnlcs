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
                        <select name="client_id" required class="form-control" @change="setClient($event.target.value)">
                            <option value="">— Choose a client —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" data-rate="{{ $client->billing_tax_rate }}" data-label="{{ $client->billing_tax_label }}" data-rates="{{ $client->billing_tax_rates->toJson() }}" {{ old('client_id', $selectedClient?->id) == $client->id ? 'selected' : '' }}>
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
                                <th style="padding:6px 8px;text-align:center;font-weight:600;color:#555;width:70px;">{{ __('common.table.qty') }}</th>
                                <th style="padding:6px 8px;text-align:center;font-weight:600;color:#555;width:70px;">{{ __('common.table.tax') }}</th>
                                <th style="padding:6px 8px;text-align:right;font-weight:600;color:#555;width:110px;">{{ __('admin.invoices.price') }}</th>
                                <th style="padding:6px 8px;text-align:right;font-weight:600;color:#555;width:100px;">{{ __('common.table.total') }}</th>
                                <th style="width:30px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr style="border-bottom:1px solid #f5f5f5;">
                                    <td style="padding:6px 8px;">
                                        <input type="text" :id="'desc-input-' + index" :name="`items[${index}][description]`" x-model="item.description"
                                               @input="onTyping(index, item.description)" @keydown.arrow-down.prevent="move(index, 1)"
                                               @keydown.arrow-up.prevent="move(index, -1)" @keydown.enter.prevent="pickActive(index)"
                                               @keydown.escape="item.show = false" @blur="setTimeout(() => item.show = false, 150)"
                                               placeholder="{{ __('admin.invoices.description_placeholder') }}" required class="form-control" style="font-size:13px;">
                                        <div x-show="item.show && matchesFor(item.description).length"
                                             x-cloak :style="item.dd ? 'position:fixed;top:' + item.dd.bottom + 'px;left:' + item.dd.left + 'px;width:' + item.dd.width + 'px;z-index:9999;background:#fff;border:1px solid #ccc;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.2);' : 'display:none;'">
                                            <div style="overflow-y:auto;max-height:220px;">
                                            <template x-for="(p, i) in pageList(item)" :key="p.name">
                                                <div @mousedown.prevent="select(index, p)"
                                                     @mouseenter="item.active = i"
                                                     :style="'padding:7px 10px;cursor:pointer;font-size:13px;' + (i === item.active ? 'background:#f0f6ff;' : '')">
                                                    <span x-text="p.name" style="font-weight:600;"></span>
                                                    <span x-show="p.amount != null" style="color:#777;float:right;" x-text="p.amount != null ? currencyPrefix + Number(p.amount).toFixed(2) + currencySuffix : ''"></span>
                                                </div>
                                            </template>
                                            </div>
                                            <div x-show="totalPages(item) > 1" style="display:flex;align-items:center;justify-content:space-between;padding:5px 8px;border-top:1px solid #eee;font-size:12px;background:#fafafa;">
                                                <button type="button" @click.prevent="prevPage(item)" :disabled="item.page <= 1"
                                                        style="border:1px solid #ccc;background:#fff;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:12px;">&laquo;</button>
                                                <span style="color:#666;" x-text="item.page + ' / ' + totalPages(item)"></span>
                                                <button type="button" @click.prevent="nextPage(item)" :disabled="item.page >= totalPages(item)"
                                                        style="border:1px solid #ccc;background:#fff;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:12px;">&raquo;</button>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:6px 8px;text-align:center;">
                                        <input type="number" :name="`items[${index}][qty]`" x-model.number="item.qty"
                                               @input="recalculate()" placeholder="1" step="1" min="1"
                                               class="form-control" style="font-size:13px;text-align:center;width:64px;">
                                    </td>
                                    <td style="padding:6px 8px;text-align:center;">
                                        <select :name="`items[${index}][tax_label]`" x-model="item.tax_label"
                                                @change="syncItemTax(index)" class="form-control"
                                                style="font-size:13px;text-align:center;min-width:120px;">
                                            <option value="">0%</option>
                                            <template x-for="(r, i) in clientRates" :key="i">
                                                <option :value="r.name" x-text="r.name"></option>
                                            </template>
                                        </select>
                                        <input type="hidden" :name="`items[${index}][tax_rate]`" :value="item.tax_rate">
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <input type="number" :name="`items[${index}][amount]`" x-model.number="item.amount"
                                               @input="recalculate()" placeholder="0.00" step="0.01" min="0" required
                                               class="form-control" style="font-size:13px;text-align:right;">
                                    </td>
                                    <td style="padding:6px 8px;text-align:right;white-space:nowrap;color:#333;font-family:monospace;font-size:13px;">
                                        <span x-text="currencyPrefix + lineTotal(item).toFixed(2) + currencySuffix">0.00</span>
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
                        <input type="date" name="due_date" required value="{{ old('due_date', now()->addDays($dueDays ?? 14)->toDateString()) }}" class="form-control">
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.payment_method') }}</strong></div>
                <div class="card-body">
                    <div class="form-group" style="margin:0;">
                        <select name="payment_method" class="form-control">
                            @foreach($gateways as $gw)
                            <option value="{{ $gw }}" {{ old('payment_method', $defaultPaymentMethod ?? '') == $gw ? 'selected' : '' }}>{{ payment_method_label((string) $gw) }}</option>
                            @endforeach
                            @unless(in_array('banktransfer', $gateways, true))
                            <option value="banktransfer" {{ old('payment_method', $defaultPaymentMethod ?? '') == 'banktransfer' ? 'selected' : '' }}>{{ __('admin.invoices.bank_transfer') }}</option>
                            @endunless
                            <option value="manual" {{ old('payment_method', $defaultPaymentMethod ?? '') == 'manual' ? 'selected' : '' }}>{{ __('admin.invoices.manual') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:15px;">
                <div class="card-header"><strong>{{ __('admin.invoices.summary') }}</strong></div>
                <div class="card-body">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px 0;color:#777;">{{ __('admin.invoices.net') }}</td>
                            <td style="padding:4px 0;"></td>
                            <td style="padding:4px 0;text-align:right;font-family:monospace;" x-text="currencyPrefix + subtotal.toFixed(2) + currencySuffix">0.00</td>
                        </tr>
                        <template x-for="g in vatBreakdown" :key="g.label">
                            <tr>
                                <td style="padding:4px 0;color:#777;" x-text="g.label"></td>
                                <td style="padding:4px 0;text-align:right;font-family:monospace;" x-text="currencyPrefix + g.amount.toFixed(2) + currencySuffix">0.00</td>
                                <td style="padding:4px 0;text-align:right;font-family:monospace;" x-text="currencyPrefix + g.net.toFixed(2) + currencySuffix">0.00</td>
                            </tr>
                        </template>
                        <tr style="border-top:2px solid #aaa;background:#f5f5f5;">
                            <td style="padding:6px 0;font-weight:700;">{{ __('admin.invoices.gross') }}</td>
                            <td style="padding:6px 0;"></td>
                            <td style="padding:6px 0;text-align:right;font-weight:700;font-family:monospace;font-size:15px;" x-text="currencyPrefix + grandTotal.toFixed(2) + currencySuffix">0.00</td>
                        </tr>
                    </table>
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
        items: [{ description: '', qty: 1, amount: 0, tax_rate: 0, tax_label: '', show: false, active: -1, dd: null, page: 1 }],
        products: @json($products),
        currencyPrefix: @json($defaultCurrency?->prefix ?? ''),
        currencySuffix: @json($defaultCurrency?->suffix ?? ''),
        taxRate: 0,
        taxLabel: '',
        clientRates: [],
        vatLabel: @json(__('admin.invoices.tax')),
        perPage: 6,
        subtotal: 0,
        taxAmount: 0,
        vatBreakdown: [],
        grandTotal: 0,
        init() {
            const sel = this.$el.querySelector('select[name="client_id"]');
            const opt = sel ? sel.options[sel.selectedIndex] : null;
            if (opt && opt.value) {
                this.clientRates = this.parseRates(opt);
                this.taxLabel = opt.dataset.label || '';
                this.taxRate = this.defaultRate();
            }
            this.items[0].tax_rate = this.taxRate;
            this.items[0].tax_label = this.taxLabel;
            this.recalculate();
        },
        setClient(value) {
            const opt = this.$el.querySelector('option[value="' + value + '"]');
            this.clientRates = value && opt ? this.parseRates(opt) : [];
            this.taxLabel = value && opt ? (opt.dataset.label || '') : '';
            this.taxRate = this.defaultRate();
            this.items.forEach(i => { i.tax_rate = this.taxRate; i.tax_label = this.taxLabel; });
            this.recalculate();
        },
        defaultRate() {
            const d = this.clientRates.find(r => r.is_default);
            return d ? d.rate : (this.clientRates.length ? this.clientRates[0].rate : 0);
        },
        syncItemTax(index) {
            const item = this.items[index];
            const r = this.clientRates.find(r => r.name === item.tax_label);
            item.tax_rate = r ? r.rate : 0;
            this.recalculate();
        },
        parseRates(opt) {
            try { return JSON.parse(opt.dataset.rates || '[]'); } catch (e) { return []; }
        },
        addItem() { this.items.push({ description: '', qty: 1, amount: 0, tax_rate: this.taxRate, tax_label: this.taxLabel, show: false, active: -1, dd: null, page: 1 }); },
        removeItem(index) { if (this.items.length > 1) { this.items.splice(index, 1); this.recalculate(); } },
        matchesFor(value) {
            const q = (value || '').trim().toLowerCase();
            return q ? this.products.filter(p => p.name.toLowerCase().includes(q)) : this.products;
        },
        totalPages(item) {
            const n = this.matchesFor(item.description).length;
            return Math.max(1, Math.ceil(n / this.perPage));
        },
        pageList(item) {
            const list = this.matchesFor(item.description);
            const start = (item.page - 1) * this.perPage;
            return list.slice(start, start + this.perPage);
        },
        prevPage(item) { if (item.page > 1) { item.page--; item.active = -1; } },
        nextPage(item) { if (item.page < this.totalPages(item)) { item.page++; item.active = -1; } },
        onTyping(index, value) {
            const item = this.items[index];
            const el = document.getElementById('desc-input-' + index);
            if (el) {
                const r = el.getBoundingClientRect();
                item.dd = { left: r.left, bottom: r.bottom + 4, width: r.width };
            }
            item.show = true;
            item.active = -1;
            item.page = 1;
            if (!this.matchesFor(value).some(p => p.name === value)) {
                this.recalculate();
            }
        },
        move(index, dir) {
            const item = this.items[index];
            const list = this.pageList(item);
            item.show = true;
            if (list.length) {
                item.active = (item.active + dir + list.length) % list.length;
            }
        },
        pickActive(index) {
            const item = this.items[index];
            const list = this.pageList(item);
            const target = item.active >= 0 ? list[item.active] : list[0];
            if (target) { this.select(index, target); }
        },
        select(index, p) {
            const item = this.items[index];
            item.description = p.name;
            item.qty = 1;
            item.amount = p.amount ?? 0;
            item.tax_rate = p.taxed ? this.taxRate : 0;
            item.tax_label = p.taxed ? this.taxLabel : '';
            item.show = false;
            this.recalculate();
        },
        lineTotal(item) { return (parseFloat(item.amount) || 0) * (parseInt(item.qty, 10) || 1); },
        fmtRate(r) { return String(Math.round(r * 100) / 100); },
        recalculate() {
            this.subtotal = this.items.reduce((s, i) => s + this.lineTotal(i), 0);
            const groups = {};
            this.items.forEach(i => {
                const rate = parseFloat(i.tax_rate) || 0;
                const label = (i.tax_label || '').trim();
                if (!label && rate <= 0) { return; }
                const key = label || (rate + '%');
                groups[key] = groups[key] || { label: key, rate: rate, amount: 0, net: 0 };
                groups[key].amount += this.lineTotal(i) * rate / 100;
                groups[key].net += this.lineTotal(i);
            });
            this.vatBreakdown = Object.values(groups).map(g => ({
                label: g.label,
                rate: g.rate,
                amount: Math.round(g.amount * 100) / 100,
                net: Math.round(g.net * 100) / 100
            })).sort((a, b) => b.rate - a.rate);
            this.taxAmount = Math.round(this.vatBreakdown.reduce((s, g) => s + g.amount, 0) * 100) / 100;
            this.grandTotal = Math.round((this.subtotal + this.taxAmount) * 100) / 100;
        }
    };
}
</script>
@endsection
