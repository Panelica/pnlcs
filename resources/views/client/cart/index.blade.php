@extends("client.layouts.app")
@section("title", "Shopping Cart")
@section("content")

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Shopping Cart</h1>
    <a href="{{ route("client.store") }}" class="text-sm text-indigo-600 hover:underline">+ Add More Products</a>
</div>

@if(session("error"))
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session("error") }}</div>
@endif

@if(empty($totals["items"]))
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
    <p class="text-slate-400 mb-4">Your cart is empty.</p>
    <a href="{{ route("client.store") }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
        Browse Products
    </a>
</div>
@else
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-4">
        @foreach($totals["items"] as $index => $item)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 flex items-start justify-between">
            <div>
                <h3 class="font-semibold">{{ $item["product_name"] }}</h3>
                <p class="text-sm text-slate-500 mt-1">
                    {{ ucfirst($item["billing_cycle"]) }}
                    @if(!empty($item["domain"])) &mdash; {{ $item["domain"] }} @endif
                </p>
                <p class="text-indigo-600 font-bold mt-2">
                    {{ $currency ? $currency->prefix : "$" }}{{ number_format($item["price"], 2) }}{{ $currency ? $currency->suffix : "" }}
                </p>
            </div>
            <form method="POST" action="{{ route("client.cart.remove", $index) }}">
                @csrf
                @method("DELETE")
                <button type="submit" class="text-red-400 hover:text-red-600 text-sm transition-colors ml-4 mt-1">Remove</button>
            </form>
        </div>
        @endforeach

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h3 class="text-sm font-semibold mb-3">Promo Code</h3>
            <form method="POST" action="{{ route("client.cart.promo") }}" class="flex gap-3">
                @csrf
                <input type="text" name="code" placeholder="Enter promo code"
                    value="{{ $totals['promo_code'] ?? '' }}"
                    class="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <button type="submit" class="bg-slate-800 hover:bg-slate-700 dark:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                    Apply
                </button>
            </form>
            @if(!empty($totals["promo_code"]))
            <p class="text-xs text-emerald-600 mt-2">Promo code <strong>{{ $totals["promo_code"] }}</strong> applied.</p>
            @endif
        </div>
    </div>

    <div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 sticky top-6">
            <h3 class="font-semibold mb-4">Order Summary</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Subtotal</span>
                    <span>{{ $currency ? $currency->prefix : "$" }}{{ number_format($totals["subtotal"], 2) }}{{ $currency ? $currency->suffix : "" }}</span>
                </div>
                @if($totals["discount"] > 0)
                <div class="flex justify-between text-emerald-600">
                    <span>Discount</span>
                    <span>&minus;{{ $currency ? $currency->prefix : "$" }}{{ number_format($totals["discount"], 2) }}{{ $currency ? $currency->suffix : "" }}</span>
                </div>
                @endif
                @if($totals["tax"] > 0)
                <div class="flex justify-between text-slate-500">
                    <span>Tax ({{ $totals["tax_rate"] }}%)</span>
                    <span>{{ $currency ? $currency->prefix : "$" }}{{ number_format($totals["tax"], 2) }}{{ $currency ? $currency->suffix : "" }}</span>
                </div>
                @endif
                <div class="border-t border-slate-200 dark:border-slate-600 pt-3 mt-3 flex justify-between font-bold text-base">
                    <span>Total</span>
                    <span class="text-indigo-600">{{ $currency ? $currency->prefix : "$" }}{{ number_format($totals["total"], 2) }}{{ $currency ? $currency->suffix : "" }}</span>
                </div>
            </div>
            <a href="{{ route("client.cart.checkout") }}" class="mt-5 block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                Proceed to Checkout
            </a>
        </div>
    </div>
</div>
@endif
@endsection
