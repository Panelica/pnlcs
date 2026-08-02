{{-- ===== DOMAIN SEARCH ===== --}}
@php
    $c = $content ?? collect();
    $dsTitle = $c->get('title')->content_value ?? __('sections.domain.title');
    $dsSubtitle = $c->get('subtitle')->content_value ?? __('sections.domain.subtitle');
    $dsPlaceholder = $c->get('placeholder')->content_value ?? __('sections.domain.placeholder');
@endphp
<section class="domain-search">
    <div class="container">
        <h2 class="section-title">{{ $dsTitle }}</h2>
        <p class="section-subtitle">{{ $dsSubtitle }}</p>
        <form action="/client/domain-search" method="GET" class="domain-search__bar">
            <div class="domain-search__icon"><i class="ri-global-line"></i></div>
            <input type="text" name="domain" class="domain-search__input" placeholder="{{ $dsPlaceholder }}" required>
            <button type="submit" class="domain-search__btn"><i class="ri-search-line"></i>{{ __('common.actions.search') }}</button>
        </form>
        <div class="domain-search__extensions">
            @if(isset($domainPricing) && $domainPricing->count())
                @foreach($domainPricing as $tld)
                <a href="/client/domain-search" class="domain-search__ext">
                    <div class="domain-search__ext-name">.{{ ltrim($tld->extension, '.') }}</div>
                    <div class="domain-search__ext-price">{{ money_fmt($tld->register_price) }}/yr</div>
                    <span class="domain-search__ext-link">{{ __('common.actions.register') }}</span>
                </a>
                @endforeach
            @else
                {{-- Fallback static TLDs --}}
                @foreach([['com', '9.99'], ['net', '11.99'], ['org', '8.99'], ['io', '29.99'], ['dev', '12.99'], ['co', '11.99'], ['biz', '14.99'], ['info', '4.99']] as $tld)
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.{{ $tld[0] }}</div>
                    <div class="domain-search__ext-price">${{ $tld[1] }}/yr</div>
                    <span class="domain-search__ext-link">{{ __('common.actions.register') }}</span>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</section>
