@extends('admin.layouts.app')
@section('title', __('messages.ksef.imported_invoice'))
@section('content')

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">{{ $imported->invoice_number ?: $imported->ksef_number }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ $imported->ksef_number }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if(filled($imported->xml))
            <a href="{{ route('admin.ksef.imported.xml', $imported) }}" class="btn btn-default btn-sm">{{ __('messages.ksef.download_xml') }}</a>
        @endif
        <a href="{{ route('admin.ksef.index', ['tab' => $imported->direction === 'sales' ? 'sales' : 'costs', 'year' => $imported->issue_date?->year ?? now()->year, 'month' => $imported->issue_date?->month ?? now()->month]) }}" class="btn btn-default btn-sm">{{ __('messages.ksef.back') }}</a>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <table class="data-table" style="width:100%;">
            <tbody>
                <tr><th style="width:220px;">{{ __('messages.ksef.direction') }}</th><td>{{ $imported->direction === 'sales' ? __('messages.ksef.tab_sales') : __('messages.ksef.tab_costs') }}</td></tr>
                <tr><th>{{ __('messages.ksef.invoice_number') }}</th><td>{{ $imported->invoice_number ?: '—' }}</td></tr>
                <tr><th>{{ __('messages.ksef.issue_date') }}</th><td>{{ $imported->issue_date?->format(date_fmt()) ?? '—' }}</td></tr>
                <tr><th>{{ __('messages.ksef.seller') }}</th><td>{{ $imported->seller_name ?: '—' }} @if($imported->seller_nip)<span style="color:var(--pn-muted);">({{ $imported->seller_nip }})</span>@endif</td></tr>
                <tr><th>{{ __('messages.ksef.buyer') }}</th><td>{{ $imported->buyer_name ?: '—' }} @if($imported->buyer_nip)<span style="color:var(--pn-muted);">({{ $imported->buyer_nip }})</span>@endif</td></tr>
                <tr><th>{{ __('messages.ksef.net') }}</th><td>{{ $imported->net !== null ? number_format((float) $imported->net, 2, ',', ' ') : '—' }} {{ $imported->currency }}</td></tr>
                <tr><th>{{ __('messages.ksef.vat') }}</th><td>{{ $imported->vat !== null ? number_format((float) $imported->vat, 2, ',', ' ') : '—' }} {{ $imported->currency }}</td></tr>
                <tr><th>{{ __('messages.ksef.gross') }}</th><td>{{ $imported->gross !== null ? number_format((float) $imported->gross, 2, ',', ' ') : '—' }} {{ $imported->currency }}</td></tr>
                <tr><th>{{ __('messages.ksef.imported_at') }}</th><td>{{ $imported->imported_at?->format(date_fmt().' H:i') ?? '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('messages.ksef.xml') }}</strong></div>
    <div class="card-body">
        @if(filled($imported->xml))
            <pre style="max-height:600px;overflow:auto;background:#f8f9fa;border:1px solid var(--border);border-radius:4px;padding:14px;font-size:12px;white-space:pre-wrap;">{{ $imported->xml }}</pre>
        @else
            <p style="color:var(--pn-muted);margin:0;">{{ __('messages.ksef.xml_missing') }}</p>
        @endif
    </div>
</div>
@endsection
