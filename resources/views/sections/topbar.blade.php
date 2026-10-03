{{-- ===== TOP BAR ===== --}}
<div class="top-bar">
    <div class="container">
        <div class="top-bar__inner">
            <div class="top-bar__left">
                <a href="mailto:{{ $brandEmail ?? 'info@panelica.com' }}" class="top-bar__item"><i class="ri-mail-line"></i> {{ $brandEmail ?? 'info@panelica.com' }}</a>
                <div class="top-bar__divider"></div>
                <a href="/client/contact" class="top-bar__item"><i class="ri-headphone-line"></i> {{ __('sections.topbar.contact') }}</a>
                <div class="top-bar__divider"></div>
                <a href="/client/tickets/create" class="top-bar__item"><i class="ri-ticket-line"></i> {{ __('sections.topbar.support_ticket') }}</a>
                <div class="top-bar__divider"></div>
                <a href="{{ $brandUrl ?? 'https://www.panelica.com' }}/blog" class="top-bar__item"><i class="ri-article-line"></i> {{ __('sections.topbar.blog') }}</a>
            </div>
            <div class="top-bar__right">
                <a href="{{ route('client.login') }}" class="top-bar__item"><i class="ri-user-line"></i> {{ __('sections.topbar.my_account') }}</a>
                <div class="top-bar__divider"></div>
                <a href="{{ route('client.register') }}" class="top-bar__item"><i class="ri-user-add-line"></i> {{ __('sections.topbar.sign_up') }}</a>
                <div class="top-bar__divider"></div>
                @if(isset($activeLanguages) && $activeLanguages->count() > 1)
                <details class="top-bar__language">
                    <summary class="top-bar__item top-bar__language-toggle">
                        <i class="ri-global-line"></i> {{ $currentLocaleName ?? __('client.topbar.language') }}
                    </summary>
                    <div class="top-bar__language-menu">
                        @foreach($activeLanguages as $language)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $language->code]) }}" class="top-bar__language-option {{ $language->code === ($currentLocale ?? app()->getLocale()) ? 'top-bar__language-option--active' : '' }}">
                                {{ $language->native_name }}
                            </a>
                        @endforeach
                    </div>
                </details>
                @else
                <span class="top-bar__item"><i class="ri-global-line"></i> {{ __('client.topbar.language') }}</span>
                @endif
                <div class="top-bar__divider"></div>
                @if(isset($customerCurrencyChoice) && $customerCurrencyChoice->count() > 1)
                {{-- The visitor's currency, kept for the session (CustomerCurrency). --}}
                <details class="top-bar__language top-bar__currency">
                    <summary class="top-bar__item top-bar__language-toggle">
                        <i class="ri-money-dollar-circle-line"></i> {{ $displayCurrency?->code ?? currency_code_default() }}
                    </summary>
                    <div class="top-bar__language-menu">
                        @foreach($customerCurrencyChoice as $choice)
                            <a href="{{ request()->fullUrlWithQuery(['currency' => $choice->code]) }}" rel="nofollow" class="top-bar__language-option {{ $choice->id === $displayCurrency?->id ? 'top-bar__language-option--active' : '' }}">
                                {{ $choice->code }}@if(trim($choice->prefix.$choice->suffix) !== '') ({{ trim($choice->prefix.' '.$choice->suffix) }})@endif
                            </a>
                        @endforeach
                    </div>
                </details>
                @elseif(($customerCurrencyEnabled ?? false) && $displayCurrency)
                {{-- A customer's account currency: changed by staff, not by a link. --}}
                <span class="top-bar__item" title="{{ __('client.topbar.currency_account') }}"><i class="ri-money-dollar-circle-line"></i> {{ $displayCurrency->code }}</span>
                @else
                {{-- The shop's own currency, not a word from the language file:
                     that printed "TRY" on every Turkish page, whatever the shop sells in. --}}
                <span class="top-bar__item"><i class="ri-money-dollar-circle-line"></i> {{ currency_code_default() }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
