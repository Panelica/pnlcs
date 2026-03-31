@extends("client.layouts.app")
@section("title", "Order a New Product")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Order a New Product</h1>
        <p class="pn-page-subtitle">Choose the perfect plan for your needs.</p>
    </div>
    @if(!empty($cart) && count($cart) > 0)
    <a href="{{ route("client.cart.index") }}" class="btn btn-outline">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        View Cart
    </a>
    @endif
</div>

@if(session("error"))
<div class="pn-alert pn-alert-error mb-16">{{ session("error") }}</div>
@endif

@forelse($groups as $group)
<div class="mb-24">
    <h2 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--border)">{{ $group->name }}</h2>
    @if($group->products->isEmpty())
        <p class="text-muted text-sm">No products available in this category.</p>
    @else
        <div class="pn-product-grid">
            @foreach($group->products as $product)
            @php
                $pricingRecord = $product->pricing->first();
                $startingPrice = null; $startingCycle = "";
                if ($pricingRecord) {
                    foreach (["monthly","quarterly","semiannually","annually","biennially","triennially"] as $cycle) {
                        if (isset($pricingRecord->{$cycle}) && (float)$pricingRecord->{$cycle} > 0) {
                            $startingPrice = $pricingRecord->{$cycle}; $startingCycle = $cycle; break;
                        }
                    }
                }
                $currPrefix = $currency?->prefix ?? "$";
                $currSuffix = $currency?->suffix ?? "";
            @endphp
            <div class="pn-card pn-product-card {{ $product->is_featured ? "featured" : "" }}">
                @if($product->is_featured)
                    <div class="pn-popular">Most Popular</div>
                @endif
                <div style="font-size:15px;font-weight:700;color:var(--primary);margin-bottom:8px">{{ $product->name }}</div>
                @if($startingPrice !== null)
                    <div class="pn-product-price">{{ $currPrefix }}{{ number_format($startingPrice, 2) }}{{ $currSuffix }} <span class="cycle">/{{ $startingCycle }}</span></div>
                @else
                    <div class="pn-product-price">Contact Us</div>
                @endif
                @if($product->description)
                    <div style="font-size:13px;color:var(--muted);line-height:1.65;margin:12px 0 16px;flex:1">{{ Str::limit(strip_tags($product->description), 120) }}</div>
                @else
                    <div style="flex:1;margin-bottom:16px"></div>
                @endif
                <a href="{{ route("client.store.configure", $product) }}" class="btn {{ $product->is_featured ? "btn-accent" : "btn-primary" }}" style="justify-content:center;text-align:center">
                    Order Now &rarr;
                </a>
            </div>
            @endforeach
        </div>
    @endif
</div>
@empty
<div class="pn-card">
    <div class="pn-empty">
        <div class="pn-empty-icon">&#128722;</div>
        <p>No products are available at this time.</p>
    </div>
</div>
@endforelse

@endsection
