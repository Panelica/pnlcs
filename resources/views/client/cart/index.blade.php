@extends("client.layouts.app")
@section("title", __("client.cart.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.cart.shopping_cart') }}</h1>
    </div>
    <a href="{{ route("client.store") }}" class="btn btn-outline">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('client.cart.add_more_products') }}
    </a>
</div>

@if(session("success"))<div class="pn-alert pn-alert-success">{{ session("success") }}</div>@endif
@if(session("error"))<div class="pn-alert pn-alert-error">{{ session("error") }}</div>@endif

@if(empty($totals["items"]))
<div class="pn-card">
    <div class="pn-empty" style="padding:64px 24px">
        <div class="pn-empty-icon">&#128722;</div>
        <p>{{ __('client.cart.empty') }}</p>
        <a href="{{ route("client.store") }}" class="btn btn-primary">{{ __('client.cart.browse_products') }}</a>
    </div>
</div>
@else
<div class="pn-cart-grid">
    <div>
        <div class="pn-card mb-16">
            <div class="pn-card-header"><span class="pn-card-title">{{ __('client.cart.cart_items') }}</span></div>
            <div class="pn-card-body-flush">
                <table class="pn-table">
                    <thead>
                        <tr>
                            <th>{{ __('common.table.product') }}</th>
                            <th>{{ __('common.table.billing_cycle') }}</th>
                            <th style="text-align:right">{{ __('common.table.price') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($totals["items"] as $key => $item)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $item["product_name"] ?? $item["name"] ?? "Product" }}</div>
                                @if(!empty($item["domain"]))<div class="text-muted text-sm">{{ $item["domain"] }}</div>@endif
                            </td>
                            <td class="text-muted" style="text-transform:capitalize">{{ $item["billing_cycle"] ?? "-" }}</td>
                            <td style="text-align:right;font-weight:700">${{ number_format($item["price"] ?? 0, 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route("client.cart.remove", $key) }}" style="display:inline">
                                    @csrf
                                    @method("DELETE")
                                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.remove') }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pn-card">
            <div class="pn-card-header"><span class="pn-card-title">{{ __('client.cart.promo_code') }}</span></div>
            <div class="pn-card-body">
                <form method="POST" action="{{ route("client.cart.promo") }}" style="display:flex;gap:8px;max-width:360px">
                    @csrf
                    <input type="text" name="code" class="form-control" placeholder="{{ __('client.cart.enter_promo_code') }}" value="{{ $totals["promo_code"] ?? "" }}">
                    <button type="submit" class="btn btn-outline" style="flex-shrink:0">{{ __('common.actions.apply') }}</button>
                </form>
                @if($totals["promo_code"] ?? false)
                <div class="pn-alert pn-alert-success mt-16">{{ __('client.cart.promo_applied', ['code' => $totals["promo_code"]]) }}</div>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="pn-card" style="position:sticky;top:80px">
            <div class="pn-card-header"><span class="pn-card-title">{{ __('client.cart.order_summary') }}</span></div>
            <div class="pn-card-body">
                <div class="pn-order-row"><span class="key">{{ __('client.cart.subtotal') }}</span><span>${{ number_format($totals["subtotal"], 2) }}</span></div>
                @if(($totals["discount"] ?? 0) > 0)
                <div class="pn-order-row" style="color:var(--success)"><span>{{ __('client.cart.discount') }}</span><span>-${{ number_format($totals["discount"], 2) }}</span></div>
                @endif
                @if(($totals["tax"] ?? 0) > 0)
                <div class="pn-order-row"><span class="key">{{ __('client.cart.tax') }} ({{ $totals["tax_rate"] ?? 0 }}%)</span><span>${{ number_format($totals["tax"], 2) }}</span></div>
                @endif
                <div class="pn-order-row"><span>{{ __('client.cart.total') }}</span><span style="color:var(--primary)">${{ number_format($totals["total"], 2) }}</span></div>
                <a href="{{ route("client.cart.checkout") }}" class="btn btn-accent" style="width:100%;justify-content:center;margin-top:16px">
                    {{ __('client.cart.checkout') }} &rarr;
                </a>
                <a href="{{ route("client.store") }}" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px">
                    {{ __('client.cart.continue_shopping') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
