@extends("client.layouts.app")
@section("title", __("client.quotes.quote_prefix", ["id" => $quote->id]))
@section("content")

<a href="{{ route('client.quotes.index') }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.quotes.back_to_quotes') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.quotes.quote_prefix', ['id' => $quote->id]) }} — {{ $quote->subject }}</h1>
        <p class="pn-page-subtitle">
            {{ __('client.quotes.issued') }} {{ $quote->date ? \Carbon\Carbon::parse($quote->date)->format(date_fmt()) : '-' }}
            @if($quote->valid_until) &nbsp;·&nbsp; {{ __('client.quotes.valid_until') }}: <strong>{{ \Carbon\Carbon::parse($quote->valid_until)->format(date_fmt()) }}</strong> @endif
        </p>
    </div>
    <span class="badge badge-{{ strtolower($quote->status) }}" style="font-size:13px;padding:5px 14px">{{ __('client.status.' . strtolower($quote->status)) }}</span>
</div>

<div class="pn-card mb-24">
    <div class="pn-card-header">
        <span class="pn-card-title">{{ __('client.quotes.line_items') }}</span>
    </div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.description') }}</th>
                    <th style="text-align:right;width:90px">{{ __('client.quotes.qty') }}</th>
                    <th style="text-align:right;width:130px">{{ __('client.quotes.unit_price') }}</th>
                    <th style="text-align:right;width:130px">{{ __('common.table.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align:right">{{ $item->quantity }}</td>
                    <td style="text-align:right">{{ money_fmt((float) $item->unit_price) }}</td>
                    <td style="text-align:right;font-weight:600">{{ money_fmt(max(0, ($item->quantity * $item->unit_price) - $item->discount)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pn-card-body" style="border-top:1px solid var(--border)">
        <div style="max-width:280px;margin-left:auto">
            @if((float) $quote->subtotal !== (float) $quote->total)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid #f1f5f9">
                <span class="text-muted">{{ __('client.cart.subtotal') }}</span>
                <span>{{ money_fmt((float) $quote->subtotal) }}</span>
            </div>
            @endif
            @if((float) $quote->tax > 0)
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13.5px;border-bottom:1px solid #f1f5f9">
                <span class="text-muted">{{ __('client.cart.tax') }}</span>
                <span>{{ money_fmt((float) $quote->tax) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:12px 0 4px;font-size:17px;font-weight:800;color:var(--primary)">
                <span>{{ __('common.table.total') }}</span>
                <span>{{ money_fmt((float) $quote->total) }}</span>
            </div>
        </div>
    </div>
</div>

@if($quote->proposal)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.quotes.proposal') }}</span></div>
    <div class="pn-card-body" style="white-space:pre-wrap">{{ $quote->proposal }}</div>
</div>
@endif

@if($actionable)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.quotes.your_decision') }}</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">{{ __('client.quotes.decision_hint') }}</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">
            <form method="POST" action="{{ route('client.quotes.accept', $quote) }}">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('client.quotes.accept_button') }}</button>
            </form>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('decline-box').style.display='block';this.style.display='none'">
                {{ __('client.quotes.decline_button') }}
            </button>
        </div>
        <div id="decline-box" style="display:none;margin-top:14px;max-width:520px">
            <form method="POST" action="{{ route('client.quotes.decline', $quote) }}">
                @csrf
                <label class="pn-label">{{ __('client.quotes.decline_reason') }}</label>
                <textarea name="reason" class="pn-input" rows="2" maxlength="2000"></textarea>
                <button type="submit" class="btn btn-danger" style="margin-top:10px">{{ __('client.quotes.decline_confirm') }}</button>
            </form>
        </div>
    </div>
</div>
@elseif($quote->status === 'Sent')
<div class="pn-alert pn-alert-warning mb-24">{{ __('client.quotes.expired_notice') }}</div>
@endif

@endsection
