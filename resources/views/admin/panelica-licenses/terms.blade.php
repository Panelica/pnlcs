{{--
    The twelve terms Central sells, as the partner page on panelica.com lists
    them: what each one costs the partner, and a box for what it is sold for.

    The four PNLCS can recur on carry the box; the other eight say so and show
    the cost only, because a product's price lives in a named column on the
    pricing table (monthly, quarterly, semiannually, annually) and invoicing,
    renewal and proration all read those names - a seven-month product would
    have no column and no period to renew on.

    Typing into one box fills the empty ones at the same price per month, and
    the margin under each box follows what is typed. A price below cost is
    shown in red and saved all the same: what to sell for is the operator's.

    $ladder   term in months => what Panelica charges the partner, in dollars
    $sellable term in months => the PNLCS cycle it is sold on
    $prices   the package whose prices these are, or null when creating one
    $suggestMonthly  a monthly price to start from, in shop currency, or null
--}}
<div class="pl-terms" data-rate="{{ $usdRate ?: '' }}">
    @foreach($ladder as $n => $usd)
    @php
        $cycle = $sellable[$n] ?? null;
        $localCost = $usdRate ? round($usd * $usdRate, 2) : null;
        $value = $cycle && $prices ? $prices->priceFor($cycle, $currency?->id) : ($cycle === 'monthly' ? $suggestMonthly : null);
    @endphp
    <div class="pl-term{{ $cycle ? '' : ' off' }}">
        <span class="t">{{ trans_choice('panelica_license.page.months', $n, ['count' => $n]) }}</span>
        <span class="c">{{ __('panelica_license.page.term_cost', ['price' => $money($usd)]) }}</span>
        @if($cycle)
        <input type="number" name="{{ $cycle }}" step="0.01" min="0.01" class="form-control pl-price"
               data-months="{{ $n }}" data-cost="{{ $localCost }}" value="{{ $value ?: '' }}"
               aria-label="{{ trans_choice('panelica_license.page.months', $n, ['count' => $n]) }}">
        <span class="m"></span>
        @else
        <span class="x" title="{{ __('panelica_license.page.term_not_billable_why') }}">{{ __('panelica_license.page.term_not_billable') }}</span>
        @endif
    </div>
    @endforeach
</div>
<div class="pl-terms-foot">
    <span>{{ __('panelica_license.page.prices_in', ['code' => $currency?->code]) }}</span>
    <span>{{ __('panelica_license.page.autofill_hint') }}</span>
</div>
