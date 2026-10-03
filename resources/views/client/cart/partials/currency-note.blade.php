{{-- Prices on this page are converted from the shop currency (CustomerCurrency):
     say at what rate, what the invoice will be in, and how a card is charged. --}}
@if(\App\Support\CustomerCurrency::converting())
@php
    $noteCurrency = \App\Support\CustomerCurrency::current();
    $noteShop = \App\Models\Currency::getDefault();
@endphp
<p class="text-muted text-sm pn-currency-note" style="margin-top:10px;line-height:1.5;">
    {{ __('client.cart.currency_note', [
        'currency' => $noteCurrency->code,
        'shop' => $noteShop?->code,
        'rate' => number_format(\App\Support\CustomerCurrency::rateOf($noteCurrency), 4),
    ]) }}
</p>
@endif
