@extends('client.layouts.app')
@section('title', 'Order a New Product')
@section('styles')
<style>
    .products-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    @media (max-width: 900px) { .products-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 560px) { .products-grid { grid-template-columns: 1fr; } }
    .product-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px; display: flex; flex-direction: column; transition: box-shadow 0.2s; }
    .product-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); border-color: #b0cce8; }
    .product-card.featured { border-color: #337ab7; }
    .product-name { font-size: 15px; font-weight: 600; color: #1a4d80; margin-bottom: 8px; }
    .product-price { font-size: 22px; font-weight: 700; color: #333; margin-bottom: 4px; }
    .product-price span { font-size: 13px; font-weight: 400; color: #999; }
    .product-desc { font-size: 13px; color: #666; line-height: 1.6; margin-bottom: 14px; flex: 1; }
    .featured-badge { display: inline-block; background: #337ab7; color: #fff; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 3px; margin-bottom: 8px; }
    .group-title { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0; }
    .group-section { margin-bottom: 30px; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Order a New Product</h1>
</div>

@if(session('error'))
<div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">{{ session('error') }}</div>
@endif

@forelse($groups as $group)
<div class="group-section">
    <div class="group-title">{{ $group->name }}</div>
    @if($group->products->isEmpty())
        <p style="color:#999; font-size:13px;">No products available in this category.</p>
    @else
        <div class="products-grid">
            @foreach($group->products as $product)
            @php
                $pricingRecord = $product->pricing->first();
                $startingPrice = null;
                $startingCycle = '';
                if ($pricingRecord) {
                    foreach (['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cycle) {
                        if (isset($pricingRecord->{$cycle}) && (float)$pricingRecord->{$cycle} > 0) {
                            $startingPrice = $pricingRecord->{$cycle};
                            $startingCycle = $cycle;
                            break;
                        }
                    }
                }
                $currPrefix = $currency?->prefix ?? '$';
                $currSuffix = $currency?->suffix ?? '';
            @endphp
            <div class="product-card {{ $product->is_featured ? 'featured' : '' }}">
                @if($product->is_featured)
                    <div><span class="featured-badge">Popular</span></div>
                @endif
                <div class="product-name">{{ $product->name }}</div>
                @if($startingPrice !== null)
                    <div class="product-price">{{ $currPrefix }}{{ number_format($startingPrice, 2) }}{{ $currSuffix }} <span>/{{ $startingCycle }}</span></div>
                @else
                    <div class="product-price">Contact Us</div>
                @endif
                @if($product->description)
                    <div class="product-desc">{{ Str::limit(strip_tags($product->description), 120) }}</div>
                @else
                    <div class="product-desc"></div>
                @endif
                <a href="{{ route('client.store.configure', $product) }}" class="btn btn-primary btn-sm" style="text-align:center;">Order Now &rarr;</a>
            </div>
            @endforeach
        </div>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center; padding:40px; color:#999;">
        No products available at this time.
    </div>
</div>
@endforelse

@endsection
