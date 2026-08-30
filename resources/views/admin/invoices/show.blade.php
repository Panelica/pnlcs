@extends('admin.layouts.app')
@section('title', 'Invoice #' . $invoice->invoice_num)
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Invoice #{{ $invoice->invoice_num }} <span class="badge-{{ strtolower($invoice->status) }}" style="font-size:14px;vertical-align:middle;">{{ ucfirst($invoice->status) }}</span></h1>
    @php $st = strtolower((string) $invoice->status); @endphp
    <div style="display:flex;gap:6px;align-items:center;">
        @if(in_array($st, ['unpaid', 'overdue', 'partially_paid', 'payment_pending']))
        <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('mark-paid-form').style.display=document.getElementById('mark-paid-form').style.display==='none'?'block':'none'">{{ __('admin.invoices.mark_paid_btn') }}</button>
        @endif
        @if(in_array($st, ['paid', 'partially_paid']))
        <button type="button" class="btn btn-warning btn-sm" onclick="document.getElementById('refund-form').style.display=document.getElementById('refund-form').style.display==='none'?'block':'none'">{{ __('admin.invoices.refund_btn') }}</button>
        @endif
        @if(!in_array($st, ['paid', 'cancelled', 'refunded']))
        <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.invoices.confirm_cancel') }}')">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">{{ __('common.actions.cancel') }}</button>
        </form>
        @endif
        <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-default btn-sm">{{ __('admin.invoices.send') }}</button>
        </form>
        @if(in_array($st, ['unpaid', 'overdue', 'partially_paid', 'payment_pending']))
        <form method="POST" action="{{ route('admin.invoices.remind', $invoice) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">{{ __('admin.invoices.remind') }}</button>
        </form>
        @endif
        <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-info btn-sm">{{ __('admin.invoices.download_pdf_btn') }}</a>
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.invoices.back') }}</a>
    </div>
</div>

@if(in_array($st, ['paid', 'partially_paid']))
<div id="refund-form" style="display:none;margin-bottom:15px;">
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.invoices.refund_invoice') }}</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invoices.refund', $invoice) }}" onsubmit="return confirm('{{ __('admin.invoices.confirm_refund') }}')">
                @csrf
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                        <label class="form-label">{{ __('admin.invoices.refund_amount') }}</label>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ number_format((float) $invoice->total, 2, '.', '') }}" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;flex:2;min-width:200px;">
                        <label class="form-label">{{ __('admin.invoices.refund_reason') }}</label>
                        <input type="text" name="reason" maxlength="500" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;padding:7px 0;margin-bottom:0;">
                            <input type="checkbox" name="gateway_refund" value="1" checked> {{ __('admin.invoices.refund_via_gateway') }}
                        </label>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm" style="margin-bottom:0;">{{ __('admin.invoices.confirm_refund_btn') }}</button>
                </div>
                <div style="font-size:12px;color:#888;margin-top:8px;">
                    <div>{{ __('admin.invoices.refund_amount_hint') }}</div>
                    <div>{{ __('admin.invoices.refund_via_gateway_hint') }}</div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(in_array($st, ['unpaid', 'overdue', 'partially_paid', 'payment_pending']))
<div id="mark-paid-form" style="display:none;margin-bottom:15px;">
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.invoices.mark_invoice_paid') }}</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}">
                @csrf
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                        <label class="form-label">{{ __('admin.invoices.gateway') }}</label>
                        <select name="gateway" class="form-control">
                            <option value="manual">{{ __('admin.invoices.manual') }}</option>
                            <option value="banktransfer">{{ __('admin.invoices.bank_transfer') }}</option>
                            <option value="paypal">{{ __('admin.invoices.paypal') }}</option>
                            <option value="stripe">{{ __('admin.invoices.stripe') }}</option>
                            <option value="credit">{{ __('admin.invoices.credit') }}</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                        <label class="form-label">{{ __('admin.invoices.transaction_id') }}</label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="{{ __('admin.invoices.transaction_id_placeholder') }}">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:120px;">
                        <label class="form-label">{{ __('admin.invoices.amount') }}</label>
                        <input type="number" name="amount" step="0.01" value="{{ $invoice->total }}" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-success btn-sm" style="margin-bottom:0;">{{ __('admin.invoices.confirm_payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">

    {{-- Left (2/3): Line Items + Payment History --}}
    <div style="grid-column:span 2;">

        <div class="card" style="margin-bottom:15px;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <strong>{{ __('admin.invoices.line_items') }}</strong>
                <form method="POST" action="{{ route('admin.invoices.items.store', $invoice) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-default btn-xs">+ {{ __('admin.invoices.add_item') }}</button>
                </form>
            </div>
            @foreach($invoice->items as $item)
            <form id="item-form-{{ $item->id }}" method="POST" action="{{ route('admin.invoices.items.update', [$invoice, $item]) }}" style="display:none;">
                @csrf @method('PUT')
            </form>
            @endforeach
            @php
            $vatGroups = [];
            foreach ($invoice->items as $it) {
                $rate = $it->tax_rate !== null ? (float) $it->tax_rate : ($it->taxed ? (float) $invoice->tax_rate : 0.0);
                if ($rate <= 0) { continue; }
                $tax = (float) $it->amount * (int) $it->qty * $rate / 100;
                $key = rtrim(rtrim(number_format($rate, 2), '0'), '.');
                $vatGroups[$key] = ($vatGroups[$key] ?? 0) + $tax;
            }
            krsort($vatGroups);
            @endphp
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('common.table.description') }}</th><th style="width:70px;text-align:center;">{{ __('common.table.qty') }}</th><th style="text-align:right;width:100px;">{{ __('admin.invoices.price') }}</th><th style="width:70px;text-align:center;">{{ __('common.table.tax') }}</th><th style="text-align:right;width:100px;">{{ __('admin.invoices.total') }}</th><th style="width:30px;"></th>
                </tr></thead>
                <tbody>
                @forelse($invoice->items as $item)
                @php $effectiveRate = $item->tax_rate !== null ? (float) $item->tax_rate : ($item->taxed ? (float) $invoice->tax_rate : 0.0); @endphp
                <tr>
                    <td>
                        <div style="font-size:10px;color:#999;text-transform:uppercase;">{{ $item->type }}</div>
                        <input type="text" form="item-form-{{ $item->id }}" name="description" value="{{ $item->description }}" class="inv-inline" data-enter-submit>
                    </td>
                    <td><input type="number" form="item-form-{{ $item->id }}" name="qty" value="{{ (int) $item->qty }}" min="1" step="1" class="inv-inline inv-num" data-enter-submit></td>
                    <td><input type="number" form="item-form-{{ $item->id }}" name="amount" value="{{ number_format((float) $item->amount, 2, '.', '') }}" step="0.01" min="0" class="inv-inline inv-num" data-enter-submit></td>
                    <td><input type="number" form="item-form-{{ $item->id }}" name="tax_rate" value="{{ $effectiveRate > 0 ? rtrim(rtrim(number_format($effectiveRate, 2, '.', ''), '0'), '.') : '' }}" step="0.01" min="0" max="100" placeholder="0" class="inv-inline inv-num" data-enter-submit></td>
                    <td style="text-align:right;font-family:monospace;white-space:nowrap;">{{ money_fmt($item->amount * (int) $item->qty) }}</td>
                    <td style="text-align:center;">
                        <form method="POST" action="{{ route('admin.invoices.items.destroy', [$invoice, $item]) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.invoices.confirm_delete_item') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;color:#d9534f;cursor:pointer;font-size:16px;padding:0 2px;" title="{{ __('admin.invoices.delete_item') }}">&times;</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">{{ __('admin.invoices.no_line_items') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="2" style="text-align:right;padding:8px 12px;color:#555;">{{ __('admin.invoices.subtotal') }}</td><td style="text-align:right;padding:8px 12px;font-weight:600;font-family:monospace;white-space:nowrap;">{{ money_fmt($invoice->subtotal) }}</td></tr>
                    @foreach($vatGroups as $rate => $amount)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">{{ __('admin.invoices.tax') }} {{ $rate }}%</td><td style="text-align:right;padding:4px 12px;font-family:monospace;white-space:nowrap;">{{ money_fmt($amount) }}</td></tr>
                    @endforeach
                    @if($invoice->tax2 > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">{{ __('admin.invoices.tax_2') }}{{ $invoice->tax_rate2 > 0 ? " (" . rtrim(rtrim(number_format((float)$invoice->tax_rate2, 2), '0'), '.') . "%)" : "" }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;white-space:nowrap;">{{ money_fmt($invoice->tax2) }}</td></tr>
                    @endif
                    @if($invoice->credit > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#5cb85c;">{{ __('admin.invoices.credit_applied') }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;color:#5cb85c;white-space:nowrap;">-{{ money_fmt($invoice->credit) }}</td></tr>
                    @endif
                    <tr style="border-top:2px solid #aaa;background:#f5f5f5;"><td colspan="2" style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;">{{ __('admin.invoices.total') }}</td><td style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;font-family:monospace;white-space:nowrap;">{{ money_fmt($invoice->total) }}</td></tr>
                </tfoot>
            </table>
        </div>

        @if($invoice->transactions->count() > 0)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>{{ __('admin.invoices.payment_history') }}</strong></div>
            <table class="data-table">
                <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('admin.invoices.gateway') }}</th><th>{{ __('admin.invoices.transaction_id') }}</th><th style="text-align:right;">{{ __('common.table.amount') }}</th></tr></thead>
                <tbody>
                @foreach($invoice->transactions as $tx)
                <tr>
                    <td>{{ $tx->date?->format(date_fmt()) }}</td>
                    <td>{{ payment_method_label((string) $tx->gateway) }}</td>
                    <td style="font-family:monospace;font-size:12px;">{{ $tx->transaction_id ?? '&mdash;' }}</td>
                    <td style="text-align:right;color:#5cb85c;font-weight:600;">+{{ money_fmt($tx->amount_in) }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($invoice->notes)
        <div class="card">
            <div class="card-header"><strong>{{ __('admin.invoices.notes') }}</strong></div>
            <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $invoice->notes }}</div>
        </div>
        @endif
    </div>

    {{-- Right (1/3) --}}
    <div>
        @if($invoice->client)
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.invoices.client_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:40%;">{{ __('admin.invoices.name') }}</td><td style="padding:4px 0;"><a href="{{ $invoice->client ? route("admin.clients.show", $invoice->client) : "#" }}" style="color:#337ab7;font-weight:600;">{{ $invoice->client?->display_name ?? "Deleted Client" }}</a></td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('common.form.email') }}</td><td style="padding:4px 0;">{{ $invoice->buyer('email') ?? '-' }}</td></tr>
                    @if($invoice->buyer('address1'))
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.name') }}</td><td style="padding:4px 0;">{{ $invoice->buyer('address1') }}<br>{{ $invoice->buyer('city') }}, {{ $invoice->buyer('state') }} {{ $invoice->buyer('postcode') }}<br>{{ $invoice->buyer('country') }}</td></tr>
                    @endif
                    @if($invoice->buyer('tax_id'))
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.tax_id') }}</td><td style="padding:4px 0;font-family:monospace;font-size:12px;">{{ $invoice->buyer('tax_id') }}</td></tr>
                    @endif
                    @foreach($invoice->buyerCustomFields() as $label => $value)
                    <tr><td style="padding:4px 0;color:#777;">{{ $label }}</td><td style="padding:4px 0;">{{ $value }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif

        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.invoices.invoice_details') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;width:45%;">{{ __('admin.invoices.invoice_hash') }}</td><td style="padding:4px 0;font-family:monospace;font-weight:600;">{{ $invoice->invoice_num }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.date') }}</td><td style="padding:4px 0;">{{ $invoice->date?->format(date_fmt()) }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.due_date') }}</td><td style="padding:4px 0;{{ ($invoice->due_date?->isPast() && $st !== 'paid') ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $invoice->due_date?->format(date_fmt()) }}</td></tr>
                    @if($invoice->payment_method)
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.payment') }}</td><td style="padding:4px 0;">{{ payment_method_label((string) $invoice->payment_method) }}</td></tr>
                    @endif
                    @if($st === 'paid' && $invoice->date_paid)
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.paid_on') }}</td><td style="padding:4px 0;color:#5cb85c;font-weight:600;">{{ $invoice->date_paid->timezone(display_tz())->format(datetime_fmt()) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.invoices.actions') }}</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                @if($st === 'paid')
                <div style="padding:8px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:3px;text-align:center;color:#3c763d;font-size:13px;">&#10003; Paid on {{ $invoice->date_paid?->timezone(display_tz())->format(datetime_fmt()) }}</div>
                @endif
                @if($st === 'cancelled')
                <div style="padding:8px;background:#f5f5f5;border:1px solid #ddd;border-radius:3px;text-align:center;color:#777;font-size:13px;">{{ __('admin.invoices.invoice_cancelled') }}</div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.invoices.activity_log') }}</div>
            <div class="panel-body" style="max-height:190px;overflow-y:auto;padding:0;">
                @forelse($activityLog as $entry)
                <div style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                    <div style="font-size:12px;color:#333;">{{ $entry->description }}</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;">{{ $entry->date->timezone(display_tz())->format(datetime_fmt()) }} &middot; {{ $entry->user ?: 'System' }}</div>
                </div>
                @empty
                <div style="padding:10px 12px;color:#999;font-size:12px;">{{ __('admin.invoices.no_activity') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
    .inv-inline { border: 1px solid transparent; background: transparent; width: 100%; font-size: 13px; font-family: inherit; padding: 2px 4px; border-radius: 3px; color: inherit; }
    .inv-inline:hover, .inv-inline:focus { border-color: #ccc; background: #fff; outline: none; }
    .inv-num { text-align: right; }
</style>
<script>
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('[data-enter-submit]')) {
        e.preventDefault();
        var form = document.getElementById(e.target.getAttribute('form'));
        if (form) { form.requestSubmit(); }
    }
});
</script>

@endsection
