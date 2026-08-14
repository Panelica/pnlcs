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
                        <small class="text-muted">{{ __('admin.invoices.refund_amount_hint') }}</small>
                    </div>
                    <div class="form-group" style="margin:0;flex:2;min-width:200px;">
                        <label class="form-label">{{ __('admin.invoices.refund_reason') }}</label>
                        <input type="text" name="reason" maxlength="500" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;min-width:180px;">
                        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="gateway_refund" value="1" checked> {{ __('admin.invoices.refund_via_gateway') }}
                        </label>
                        <small class="text-muted">{{ __('admin.invoices.refund_via_gateway_hint') }}</small>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm" style="margin-bottom:0;">{{ __('admin.invoices.confirm_refund_btn') }}</button>
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
            <div class="card-header"><strong>{{ __('admin.invoices.line_items') }}</strong></div>
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('common.table.description') }}</th><th style="width:60px;text-align:center;">{{ __('admin.invoices.taxed') }}</th><th style="text-align:right;width:100px;">{{ __('common.table.amount') }}</th>
                </tr></thead>
                <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td><span style="font-size:11px;color:#999;text-transform:uppercase;margin-right:4px;">{{ $item->type }}</span>{{ $item->description }}</td>
                    <td style="text-align:center;">{!! $item->taxed ? '&#10003;' : '&mdash;' !!}</td>
                    <td style="text-align:right;font-family:monospace;">{{ money_fmt($item->amount) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;padding:20px;">{{ __('admin.invoices.no_line_items') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="2" style="text-align:right;padding:8px 12px;color:#555;">{{ __('admin.invoices.subtotal') }}</td><td style="text-align:right;padding:8px 12px;font-weight:600;font-family:monospace;">{{ money_fmt($invoice->subtotal) }}</td></tr>
                    @if($invoice->tax > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">{{ __('admin.invoices.tax') }}{{ $invoice->tax_rate > 0 ? " (" . $invoice->tax_rate . "%)" : "" }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;">{{ money_fmt($invoice->tax) }}</td></tr>
                    @endif
                    @if($invoice->tax2 > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#555;">{{ __('admin.invoices.tax_2') }}{{ $invoice->tax_rate2 > 0 ? " (" . $invoice->tax_rate2 . "%)" : "" }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;">{{ money_fmt($invoice->tax2) }}</td></tr>
                    @endif
                    @if($invoice->credit > 0)
                    <tr><td colspan="2" style="text-align:right;padding:4px 12px;color:#5cb85c;">{{ __('admin.invoices.credit_applied') }}</td><td style="text-align:right;padding:4px 12px;font-family:monospace;color:#5cb85c;">-{{ money_fmt($invoice->credit) }}</td></tr>
                    @endif
                    <tr style="border-top:2px solid #aaa;background:#f5f5f5;"><td colspan="2" style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;">{{ __('admin.invoices.total') }}</td><td style="text-align:right;padding:8px 12px;font-weight:700;font-size:14px;font-family:monospace;">{{ money_fmt($invoice->total) }}</td></tr>
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
                    <td style="text-transform:capitalize;">{{ $tx->gateway }}</td>
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
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.invoices.payment') }}</td><td style="padding:4px 0;text-transform:capitalize;">{{ $invoice->payment_method }}</td></tr>
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
    </div>
</div>

@endsection
