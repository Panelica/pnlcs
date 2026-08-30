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
            @if(!empty($company['logo']))
                {{-- A filesystem path, resolved and checked by the service: the
                     PDF renderer does not fetch URLs. --}}
                <img src="{{ $company['logo'] }}" style="max-height:50px; max-width:200px; margin-bottom:8px;" alt="">
            @endif
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
                {{-- Buyer as it stood when the invoice was issued (issue #7). For VAT
                     the address and tax id on the document must be the ones that applied
                     on the invoice date, so these read the snapshot; invoices issued
                     before snapshots existed fall back to the live client record. --}}
                <strong>{{ $invoice->buyer('first_name') }} {{ $invoice->buyer('last_name') }}</strong><br>
                @if($invoice->buyer('company_name')){{ $invoice->buyer('company_name') }}<br>@endif
                @if($invoice->buyer('address1')){{ $invoice->buyer('address1') }}<br>@endif
                @if($invoice->buyer('address2')){{ $invoice->buyer('address2') }}<br>@endif
                @if($invoice->buyer('city')){{ $invoice->buyer('city') }}, {{ $invoice->buyer('state') }} {{ $invoice->buyer('postcode') }}<br>@endif
                @if($invoice->buyer('country')){{ $invoice->buyer('country') }}<br>@endif
                @if($invoice->buyer('tax_id')){{ __('pdf.tax_id') ?? 'Tax ID' }}: {{ $invoice->buyer('tax_id') }}<br>@endif
                {{-- Custom fields marked "show on invoice" (e.g. NIP), as frozen
                     at issue time; invoices before snapshots fall back to the
                     live client record. --}}
                @foreach($invoice->buyerCustomFields() as $label => $value)
                    {{ $label }}: {{ $value }}<br>
                @endforeach
                {{ $invoice->buyer('email') }}
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
                <tr><td class="meta-label">{{ __('pdf.payment_method') }}:</td><td>{{ payment_method_label((string) $invoice->payment_method) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 38%;">{{ __('common.table.description') }}</th>
                <th class="text-right" style="width: 7%;">{{ __('common.table.qty') }}</th>
                <th class="text-right" style="width: 14%;">{{ __('common.table.price') }}</th>
                <th class="text-right" style="width: 13%;">{{ __('common.table.tax') }}</th>
                <th class="text-right" style="width: 28%;">{{ __('admin.invoices.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
            @php $rate = $item->tax_rate !== null ? (float)$item->tax_rate : ($item->taxed ? (float)$invoice->tax_rate : 0.0); @endphp
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ (int) $item->qty }}</td>
                <td class="text-right">{{ money_fmt((float)$item->amount) }}</td>
                <td class="text-right">{{ $rate > 0 ? rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%' : '—' }}</td>
                <td class="text-right">{{ money_fmt((float)$item->amount * (int)$item->qty) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center; color:#999;">{{ __('pdf.no_items') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @php
    $vatGroups = [];
    foreach ($invoice->items as $it) {
        $r = $it->tax_rate !== null ? (float) $it->tax_rate : ($it->taxed ? (float) $invoice->tax_rate : 0.0);
        if ($r <= 0) { continue; }
        $tx = (float) $it->amount * (int) $it->qty * $r / 100;
        $key = rtrim(rtrim(number_format($r, 2), '0'), '.');
        $vatGroups[$key] = ($vatGroups[$key] ?? 0) + $tx;
    }
    krsort($vatGroups);
    @endphp

    <div class="totals">
        <table>
            <tr><td>{{ __('admin.invoices.net') }}:</td><td class="text-right">{{ money_fmt((float)$invoice->subtotal) }}</td></tr>
            @foreach($vatGroups as $rateKey => $amount)
            <tr><td>{{ __('pdf.tax') }} {{ $rateKey }}%:</td><td class="text-right">{{ money_fmt($amount) }}</td></tr>
            @endforeach
            {{-- The second tax. An invoice has carried two since the tax screen
                 grew a level for each; this document showed only the first, so
                 the lines did not add up to the total being asked for. --}}
            @if((float)($invoice->tax2 ?? 0) > 0)
            <tr><td>{{ __('pdf.tax') }} 2{{ $invoice->tax_rate2 ? ' ('.rtrim(rtrim(number_format((float)$invoice->tax_rate2, 2), '0'), '.').'%)' : '' }}:</td><td class="text-right">{{ money_fmt((float)$invoice->tax2) }}</td></tr>
            @endif
            @if((float)$invoice->credit > 0)
            <tr><td>{{ __('pdf.credit') }}:</td><td class="text-right">-{{ money_fmt((float)$invoice->credit) }}</td></tr>
            @endif
            <tr class="total-row"><td>{{ __('admin.invoices.gross') }}:</td><td class="text-right">{{ money_fmt((float)$invoice->total) }}</td></tr>
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
