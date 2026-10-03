{{-- The currency prices are shown in (CustomerCurrency). A visitor picks it
     for the session; a customer's account currency is changed by staff. --}}
@if(isset($customerCurrencyChoice) && $customerCurrencyChoice->count() > 1)
<div class="pn-nav-item pn-currency-selector" style="position:relative;">
    <button type="button" class="pn-nav-link" onclick="this.parentElement.classList.toggle('open')" style="gap:6px;" aria-label="{{ __('client.topbar.currency') }}">
        <span>{{ $displayCurrency?->code }}</span>
        <svg class="pn-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="pn-dropdown" style="right:0;left:auto;min-width:140px;">
        @foreach($customerCurrencyChoice as $choice)
        <a href="{{ request()->fullUrlWithQuery(['currency' => $choice->code]) }}" rel="nofollow" style="{{ $choice->id === $displayCurrency?->id ? 'background:var(--primary-light);color:var(--primary);' : '' }}">
            {{ $choice->code }}@if(trim($choice->prefix.$choice->suffix) !== '') ({{ trim($choice->prefix.' '.$choice->suffix) }})@endif
        </a>
        @endforeach
    </div>
</div>
@elseif(($customerCurrencyEnabled ?? false) && $displayCurrency && auth()->check())
<div class="pn-nav-item pn-currency-selector">
    <span class="pn-nav-link" title="{{ __('client.topbar.currency_account') }}">{{ $displayCurrency->code }}</span>
</div>
@endif
