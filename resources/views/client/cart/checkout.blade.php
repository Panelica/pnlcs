@extends("client.layouts.app")
@section("title", "Checkout")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">Review & Complete Order</h1>
    <p class="text-slate-500 mt-1">Please review your order before completing payment.</p>
</div>

@if($errors->any())
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
    <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route("client.cart.process") }}">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="font-semibold mb-4">Order Items</h2>
                <div class="space-y-3">
                    @foreach($totals["items"] as $item)
                    <div class="flex justify-between items-start py-3 border-b border-slate-100 dark:border-slate-700 last:border-0">
                        <div>
                            <p class="font-medium text-sm">{{ $item["product_name"] }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ ucfirst($item["billing_cycle"]) }}
                                @if(!empty($item["domain"])) &mdash; {{ $item["domain"] }} @endif
                            </p>
                        </div>
                        <span class="font-semibold text-sm">
                            {{ $currency ? $currency->prefix : "$" }}{{ number_format($item["price"], 2) }}{{ $currency ? $currency->suffix : "" }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="font-semibold mb-4">Payment Method</h2>
                <div class="space-y-3">
                    @foreach($paymentMethods as $key => $label)
                    <label class="flex items-center gap-3 p-4 border border-slate-200 dark:border-slate-600 rounded-lg cursor-pointer hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 transition-colors">
                        <input type="radio" name="payment_method" value="{{ $key }}" class="text-indigo-600" {{ $loop->first ? "checked" : "" }}>
                        <span class="font-medium text-sm">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
                @error("payment_method") <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 text-indigo-600" {{ old("terms") ? "checked" : "" }}>
                    <span class="text-sm text-slate-600 dark:text-slate-400">
                        I agree to the <a href="#" class="text-indigo-600 hover:underline">Terms of Service</a> and
                        <a href="#" class="text-indigo-600 hover:underline">Privacy Policy</a>.
                    </span>
                </label>
                @error("terms") <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
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
                        <span>Total Due</span>
                        <span class="text-indigo-600">{{ $currency ? $currency->prefix : "$" }}{{ number_format($totals["total"], 2) }}{{ $currency ? $currency->suffix : "" }}</span>
                    </div>
                </div>
                <button type="submit" class="mt-5 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                    Complete Order
                </button>
                <a href="{{ route("client.cart.index") }}" class="mt-3 block text-center text-sm text-slate-400 hover:text-slate-600">
                    &larr; Back to Cart
                </a>
            </div>
        </div>
    </div>
</form>
@endsection
