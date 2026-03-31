@extends("client.layouts.app")
@section("title", "Order — Browse Products")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">Order a New Product</h1>
    <p class="text-slate-500 mt-1">Choose a product to get started.</p>
</div>

@if(session("error"))
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session("error") }}</div>
@endif

@forelse($groups as $group)
<div class="mb-10">
    <h2 class="text-lg font-semibold mb-4 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 pb-2">
        {{ $group->name }}
    </h2>

    @if($group->products->isEmpty())
        <p class="text-slate-400 text-sm">No products available in this category.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($group->products as $product)
            @php
                $featuredClass = $product->is_featured ? "ring-2 ring-indigo-400" : "";
                $pricingRecord = $product->pricing->first();
                $startingPrice = null;
                if ($pricingRecord) {
                    foreach (["monthly","quarterly","semiannually","annually","biennially","triennially"] as $cycle) {
                        if (isset($pricingRecord->{$cycle}) && (float)$pricingRecord->{$cycle} > 0) {
                            $startingPrice = $pricingRecord->{$cycle};
                            break;
                        }
                    }
                }
                $currPrefix = $currency ? $currency->prefix : "$";
                $currSuffix = $currency ? $currency->suffix : "";
            @endphp
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col {{ $featuredClass }}">
                @if($product->is_featured)
                <div class="mb-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Most Popular</span>
                </div>
                @endif

                <h3 class="text-base font-semibold mb-2">{{ $product->name }}</h3>

                @if($product->description)
                <p class="text-sm text-slate-500 mb-4 flex-1">{{ Str::limit($product->description, 120) }}</p>
                @endif

                @if($startingPrice !== null)
                <div class="mb-4">
                    <span class="text-slate-400 text-xs">Starting from</span>
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ $currPrefix }}{{ number_format($startingPrice, 2) }}{{ $currSuffix }}
                        <span class="text-sm font-normal text-slate-400">/mo</span>
                    </div>
                </div>
                @else
                <div class="mb-4 text-sm text-slate-400">Contact us for pricing</div>
                @endif

                <a href="{{ route("client.store.configure", $product->slug) }}"
                   class="mt-auto block text-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Order Now
                </a>
            </div>
            @endforeach
        </div>
    @endif
</div>
@empty
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
    <p class="text-slate-400">No products are currently available. Please check back soon.</p>
</div>
@endforelse

@endsection
