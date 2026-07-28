<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 30px; }
        .header-left { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { display: table-cell; width: 50%; vertical-align: top; text-align: right; }
        .company-name { font-size: 22px; font-weight: bold; color: #405189; margin-bottom: 5px; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #405189; }
        .invoice-number { font-size: 14px; color: #666; margin-top: 5px; }
        .meta-table { width: 100%; margin-bottom: 25px; }
        .meta-table td { padding: 3px 0; }
        .meta-label { font-weight: bold; color: #555; width: 120px; }
        .addresses { display: table; width: 100%; margin-bottom: 30px; }
        .address-box { display: table-cell; width: 50%; vertical-align: top; }
        .address-box h4 { font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 8px; letter-spacing: 1px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table thead th { background: #405189; color: #fff; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .items-table tbody td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .items-table tbody tr:last-child td { border-bottom: 2px solid #405189; }
        .text-right { text-align: right; }
        .totals { width: 300px; margin-left: auto; }
        .totals table { width: 100%; }
        .totals td { padding: 6px 12px; }
        .totals .total-row { font-size: 16px; font-weight: bold; color: #405189; border-top: 2px solid #405189; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 15px; }
        .notes { margin-top: 20px; padding: 12px; background: #f9fafb; border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company['name'] }}</div>
            @if($company['address'])<div>{{ $company['address'] }}</div>@endif
            @if($company['city'])<div>{{ $company['city'] }} {{ $company['country'] }}</div>@endif
            @if($company['phone'])<div>{{ $company['phone'] }}</div>@endif
            @if($company['email'])<div>{{ $company['email'] }}</div>@endif
            @if($company['tax_id'])<div>{{ __('pdf.tax_id') }}: {{ $company['tax_id'] }}</div>@endif
        </div>
        <div class="header-right">
            <div class="invoice-title">{{ __('pdf.invoice') }}</div>
            <div class="invoice-number">#{{ $invoice->invoice_num ?? $invoice->id }}</div>
            <div style="margin-top: 10px;">
                @php
                    $statusClass = match(strtolower($invoice->status)) {
                        'paid' => 'status-paid',
                        'overdue' => 'status-overdue',
                        'cancelled', 'canceled' => 'status-cancelled',
                        default => 'status-unpaid',
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $invoice->status }}</span>
            </div>
        </div>
    </div>

    <div class="addresses">
        <div class="address-box">
            <h4>{{ __('pdf.bill_to') }}</h4>
            @if($invoice->client)
                <strong>{{ $invoice->client->first_name }} {{ $invoice->client->last_name }}</strong><br>
                @if($invoice->client->company_name){{ $invoice->client->company_name }}<br>@endif
                @if($invoice->client->address1){{ $invoice->client->address1 }}<br>@endif
                @if($invoice->client->city){{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->postcode }}<br>@endif
                @if($invoice->client->country){{ $invoice->client->country }}<br>@endif
                {{ $invoice->client->email }}
            @endif
        </div>
        <div class="address-box">
            <h4>{{ __('pdf.invoice_details') }}</h4>
            <table class="meta-table">
                <tr><td class="meta-label">{{ __('pdf.invoice_date') }}:</td><td>{{ $invoice->date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td class="meta-label">{{ __('pdf.due_date') }}:</td><td>{{ $invoice->due_date?->format(date_fmt()) ?? '-' }}</td></tr>
                @if($invoice->date_paid)
                <tr><td class="meta-label">{{ __('pdf.date_paid') }}:</td><td>{{ $invoice->date_paid->format(date_fmt()) }}</td></tr>
                @endif
                @if($invoice->payment_method)
                <tr><td class="meta-label">{{ __('pdf.payment_method') }}:</td><td>{{ ucfirst($invoice->payment_method) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 60%;">{{ __('common.table.description') }}</th>
                <th class="text-right" style="width: 20%;">{{ __('pdf.taxed') }}</th>
                <th class="text-right" style="width: 20%;">{{ __('common.table.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ $item->taxed ? __('common.status.yes') : __('common.status.no') }}</td>
                <td class="text-right">${{ number_format((float)$item->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center; color:#999;">{{ __('pdf.no_items') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>{{ __('pdf.subtotal') }}:</td><td class="text-right">${{ number_format((float)$invoice->subtotal, 2) }}</td></tr>
            @if((float)$invoice->tax > 0)
            <tr><td>{{ __('pdf.tax') }}:</td><td class="text-right">${{ number_format((float)$invoice->tax, 2) }}</td></tr>
            @endif
            @if((float)$invoice->credit > 0)
            <tr><td>{{ __('pdf.credit') }}:</td><td class="text-right">-${{ number_format((float)$invoice->credit, 2) }}</td></tr>
            @endif
            <tr class="total-row"><td>{{ __('pdf.total') }}:</td><td class="text-right">${{ number_format((float)$invoice->total, 2) }}</td></tr>
        </table>
    </div>

    @if($invoice->notes)
    <div class="notes">
        <strong>{{ __('pdf.notes') }}:</strong><br>
        {{ $invoice->notes }}
    </div>
    @endif

    <div class="footer">
        {{ $company['name'] }} @if($company['domain'])&mdash; {{ $company['domain'] }}@endif
    </div>
</div>
</body>
</html>
