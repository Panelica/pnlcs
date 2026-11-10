{{--
    The twelve terms Central sells, as the partner page on panelica.com lists
    them: what each one costs the partner, and a box for what it is sold for.

    All twelve are sold here. One, three, six and twelve months are stored on
    the pricing column named after their cycle; the other eight are stored as
    product terms, and the shop prices, dates and renews them by their length
    (App\Services\BillingCycleHelper). Clearing a box withdraws that term.

    Typing into one box fills the empty ones at the same price per month, and
    the margin under each box follows what is typed. A price below cost is
    shown in red and saved all the same: what to sell for is the operator's.

    $ladder   term in months => what Panelica charges the partner, in dollars
    $prices   the package whose prices these are, or null when creating one
    $suggestMonthly  a monthly price to start from, in shop currency, or null
--}}
<div class="pl-terms">
    @foreach($ladder as $n => $usd)
    @php
        $localCost = $usdRate ? round($usd * $usdRate, 2) : null;
        $cycle = \App\Services\BillingCycleHelper::cycleForMonths($n);
        $value = $prices ? $prices->priceFor($cycle, $currency?->id) : ($n === 1 ? $suggestMonthly : null);
    @endphp
    <div class="pl-term">
        <span class="t">{{ trans_choice('panelica_license.page.months', $n, ['count' => $n]) }}</span>
        <span class="c">{{ __('panelica_license.page.term_cost', ['price' => $money($usd)]) }}</span>
        <input type="number" name="term[{{ $n }}]" step="0.01" min="0.01" class="form-control pl-price"
               data-months="{{ $n }}" data-cost="{{ $localCost }}" value="{{ $value ?: '' }}"
               aria-label="{{ trans_choice('panelica_license.page.months', $n, ['count' => $n]) }}">
        <span class="m"></span>
    </div>
    @endforeach
</div>
<div class="pl-terms-foot">
    <span>{{ __('panelica_license.page.prices_in', ['code' => $currency?->code]) }}</span>
    <span>{{ __('panelica_license.page.autofill_hint') }}</span>
</div>
