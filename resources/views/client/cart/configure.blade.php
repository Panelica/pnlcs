@extends("client.layouts.app")
@section("title", "Configure — " . $product->name)
@section("content")

<div class="mb-6">
    <a href="{{ route("client.store") }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to Products</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h1 class="text-xl font-bold mb-2">{{ $product->name }}</h1>
            @if($product->description)
            <p class="text-slate-500 text-sm mb-6">{{ $product->description }}</p>
            @endif

            <form method="POST" action="{{ route("client.cart.add") }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if(!empty($cycles))
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Billing Cycle</h3>
                    <div class="space-y-3">
                        @foreach($cycles as $key => $cycle)
                        @php $isFirst = $loop->first; @endphp
                        <label class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-600 rounded-lg cursor-pointer hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 transition-colors">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="billing_cycle" value="{{ $key }}" class="text-indigo-600" {{ $isFirst ? "checked" : "" }}>
                                <span class="font-medium">{{ $cycle["label"] }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-indigo-600">
                                    {{ $currency ? $currency->prefix : "$" }}{{ number_format($cycle["price"], 2) }}{{ $currency ? $currency->suffix : "" }}
                                </span>
                                <span class="text-xs text-slate-400 block">{{ strtolower($cycle["label"]) }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    @error("billing_cycle") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @else
                <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
                    No pricing has been configured for this product yet.
                </div>
                @endif

                @if($product->show_domain_options)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Domain</h3>
                    <input type="text" name="domain" placeholder="yourdomain.com"
                        class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        value="{{ old("domain") }}">
                    @error("domain") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @endif

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                    Add to Cart
                </button>
            </form>
        </div>
    </div>

    <div>
        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <h3 class="font-semibold mb-4 text-sm">Order Summary</h3>
            <div class="text-sm text-slate-600 dark:text-slate-400">
                <div class="flex justify-between mb-2">
                    <span>{{ $product->name }}</span>
                    <span id="summary-price">—</span>
                </div>
                <div class="border-t border-slate-200 dark:border-slate-600 pt-3 mt-3 font-semibold flex justify-between">
                    <span>Total</span>
                    <span id="summary-total">—</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const radios = document.querySelectorAll("input[name=billing_cycle]");
    const prices = {
        @foreach($cycles as $key => $cycle)
        "{{ $key }}": "{{ $currency ? $currency->prefix : "$" }}{{ number_format($cycle["price"], 2) }}{{ $currency ? $currency->suffix : "" }}",
        @endforeach
    };

    function updateSummary() {
        const selected = document.querySelector("input[name=billing_cycle]:checked");
        if (selected && prices[selected.value]) {
            document.getElementById("summary-price").textContent = prices[selected.value];
            document.getElementById("summary-total").textContent = prices[selected.value];
        }
    }

    radios.forEach(r => r.addEventListener("change", updateSummary));
    updateSummary();
});
</script>
@endsection
