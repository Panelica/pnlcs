@extends('admin.layouts.app')
@section('title', __('admin.quotes.title') . ' #' . $quote->id)
@section('content')
<div class="page-header">
    <div>
        <h1>Quote #{{ $quote->id }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;">{{ $quote->client?->full_name ?? 'N/A' }} &bull; {{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</div>
    </div>
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.quotes.back') }}</a>
</div>

<div style="display:grid;grid-template-columns:1fr 260px;gap:15px;">
    <div>
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <strong>{{ __('admin.quotes.line_items') }}</strong>
                @php $badgeClass = match($quote->status) { 'Accepted'=>'badge-active', 'Sent'=>'badge-open', 'Declined'=>'badge-cancelled', default=>'badge-draft' }; @endphp
                <span class="{{ $badgeClass }}">{{ $quote->status }}</span>
            </div>
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('common.table.description') }}</th><th style="text-align:right;">{{ __('admin.quotes.qty') }}</th><th style="text-align:right;">{{ __('admin.quotes.unit_price') }}</th><th style="text-align:right;">{{ __('common.table.discount') }}</th><th style="text-align:right;">{{ __('common.table.amount') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($quote->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td style="text-align:right;">{{ $item->quantity }}</td>
                        <td style="text-align:right;">${{ number_format($item->unit_price,2) }}</td>
                        <td style="text-align:right;">{{ $item->discount>0?'$'.number_format($item->discount,2):'-' }}</td>
                        <td style="text-align:right;font-weight:600;font-family:monospace;">${{ number_format(max(0,($item->quantity*$item->unit_price)-$item->discount),2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">{{ __('admin.quotes.no_line_items') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot style="border-top:2px solid #aaa;">
                    <tr><td colspan="4" style="padding:8px 12px;text-align:right;font-weight:600;">{{ __('admin.quotes.subtotal') }}</td><td style="padding:8px 12px;text-align:right;font-weight:600;font-family:monospace;">${{ number_format($quote->subtotal,2) }}</td></tr>
                    @if($quote->tax>0)<tr><td colspan="4" style="padding:4px 12px;text-align:right;color:#777;">{{ __('admin.quotes.tax') }}</td><td style="padding:4px 12px;text-align:right;font-family:monospace;">${{ number_format($quote->tax,2) }}</td></tr>@endif
                    <tr style="background:#f5f5f5;"><td colspan="4" style="padding:8px 12px;text-align:right;font-weight:700;font-size:14px;">{{ __('admin.quotes.total') }}</td><td style="padding:8px 12px;text-align:right;font-weight:700;font-size:14px;font-family:monospace;">${{ number_format($quote->total,2) }}</td></tr>
                </tfoot>
            </table>
        </div>

        @if($quote->proposal)
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>{{ __('admin.quotes.proposal') }}</strong></div>
            <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;line-height:1.6;">{{ $quote->proposal }}</div>
        </div>
        @endif

        @if($quote->notes || $quote->customer_notes)
        <div class="card">
            <div class="card-header"><strong>{{ __('admin.quotes.notes') }}</strong></div>
            <div class="card-body">
                @if($quote->notes)
                <div style="margin-bottom:10px;">
                    <p style="font-size:11px;font-weight:600;color:#777;text-transform:uppercase;margin-bottom:4px;">{{ __('admin.quotes.admin_notes') }}</p>
                    <p style="font-size:13px;color:#555;white-space:pre-wrap;">{{ $quote->notes }}</p>
                </div>
                @endif
                @if($quote->customer_notes)
                <div>
                    <p style="font-size:11px;font-weight:600;color:#777;text-transform:uppercase;margin-bottom:4px;">{{ __('admin.quotes.customer_notes') }}</p>
                    <p style="font-size:13px;color:#555;white-space:pre-wrap;">{{ $quote->customer_notes }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div>
        <div class="panel" style="margin-bottom:15px;">
            <div class="panel-heading panel-primary">{{ __('admin.quotes.quote_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.quotes.client') }}</td><td style="padding:4px 0;font-weight:600;">{{ $quote->client?->full_name ?? 'N/A' }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.quotes.date') }}</td><td style="padding:4px 0;">{{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.quotes.valid_until') }}</td><td style="padding:4px 0;">{{ \Carbon\Carbon::parse($quote->valid_until)->format('d M Y') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#777;">{{ __('admin.quotes.items') }}</td><td style="padding:4px 0;">{{ $quote->items->count() }}</td></tr>
                </table>
            </div>
        </div>
        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.quotes.actions') }}</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-default btn-sm" style="width:100%;text-align:center;">{{ __('admin.quotes.edit_quote') }}</a>
                @if($quote->status === 'Draft')
                <form method="POST" action="{{ route('admin.quotes.send', $quote) }}">
                    @csrf<button type="submit" class="btn btn-primary btn-sm" style="width:100%;">{{ __('admin.quotes.send_to_client') }}</button>
                </form>
                @endif
                @if($quote->status === 'Sent')
                <form method="POST" action="{{ route('admin.quotes.accept', $quote) }}">
                    @csrf<button type="submit" class="btn btn-success btn-sm" style="width:100%;">{{ __('admin.quotes.accept_quote') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.quotes.decline', $quote) }}">
                    @csrf<button type="submit" class="btn btn-danger btn-sm" style="width:100%;">{{ __('admin.quotes.decline_quote') }}</button>
                </form>
                @endif
                @if($quote->status === 'Accepted')
                <form method="POST" action="{{ route('admin.quotes.convert', $quote) }}">
                    @csrf<button type="submit" class="btn btn-primary btn-sm" style="width:100%;">{{ __('admin.quotes.convert_to_invoice') }}</button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" onsubmit="return confirm('{{ __('admin.quotes.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-default btn-sm" style="width:100%;color:#d9534f;">{{ __('admin.quotes.delete_quote') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
