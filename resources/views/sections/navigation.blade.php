{{-- ===== MAIN NAVIGATION ===== --}}
<nav class="main-nav" x-data="{ scrolled: false }" @scroll.window="scrolled = (window.scrollY > 10)" :class="{ 'scrolled': scrolled }">
    <div class="container">
        <div class="main-nav__inner">
            <a href="{{ route('home') }}" class="main-nav__brand">
                @if(!empty($customLogo))
                    <img src="{{ $customLogo }}" alt="{{ $brandName ?? 'PNLCS' }}">
                @else
                    {{ $brandName ?? 'PNLCS' }}<span class="main-nav__brand-dot"></span>
                @endif
            </a>

            <div class="main-nav__menu">
                <div class="main-nav__item">
                    <span class="main-nav__link">{{ __('sections.nav.domains') }} <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__dropdown">
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-search-line"></i> {{ __('sections.nav.domain_search') }}</a>
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-exchange-line"></i> {{ __('sections.nav.domain_transfer') }}</a>
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-file-search-line"></i> {{ __('sections.nav.whois_lookup') }}</a>
                    </div>
                </div>
                <div class="main-nav__item">
                    <span class="main-nav__link">{{ __('sections.nav.hosting') }} <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__mega" style="min-width: 480px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> {{ __('sections.nav.shared_hosting') }}</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-wordpress-line"></i> {{ __('sections.nav.wordpress_hosting') }}</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-building-line"></i> {{ __('sections.nav.business_hosting') }}</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-group-line"></i> {{ __('sections.nav.reseller_hosting') }}</a>
                        </div>
                        <div class="main-nav__mega-promo">
                            <span class="main-nav__mega-promo-text"><i class="ri-gift-2-line"></i> {{ __('sections.nav.promo_text') }}</span>
                            <a href="{{ route('client.register') }}" class="main-nav__mega-promo-btn">{{ __('sections.nav.claim_now') }}</a>
                        </div>
                    </div>
                </div>
                <div class="main-nav__item">
                    <span class="main-nav__link">{{ __('sections.nav.servers') }} <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__dropdown">
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-cloud-line"></i> {{ __('sections.nav.vps_server') }}</a>
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-hard-drive-2-line"></i> {{ __('sections.nav.vds_server') }}</a>
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> {{ __('sections.nav.dedicated_server') }}</a>
                    </div>
                </div>
                <div class="main-nav__item">
                    <a href="/client/knowledgebase" class="main-nav__link">{{ __('sections.nav.knowledge_base') }}</a>
                </div>
                <div class="main-nav__item">
                    <a href="/client/store" class="main-nav__link">{{ __('sections.nav.store') }}</a>
                </div>
            </div>

            <div class="main-nav__right">
                @if($darkModeEnabled ?? false)
                <button onclick="toggleDarkMode()" class="dark-toggle" title="Toggle dark mode" style="background:none;border:none;color:rgba(255,255,255,0.7);font-size:18px;cursor:pointer;padding:8px;transition:color 0.2s;">
                    <i class="ri-sun-line" id="lightIcon" style="display:none;"></i>
                    <i class="ri-moon-line" id="darkIcon"></i>
                </button>
                @endif
                <a href="/client/cart" class="main-nav__cart"><i class="ri-shopping-cart-2-line"></i></a>
                <a href="{{ route('client.login') }}" class="main-nav__login">{{ __('common.actions.login') }}</a>
                <button class="main-nav__hamburger" @click="mobileMenu = !mobileMenu"><i class="ri-menu-line"></i></button>
            </div>
        </div>
    </div>

    <div class="main-nav__mobile-menu" x-show="mobileMenu" x-transition @click.away="mobileMenu = false" style="display: none;">
        <a href="/client/domain-search">{{ __('sections.nav.domain_search') }}</a>
        <a href="/client/store">{{ __('sections.nav.hosting_plans') }}</a>
        <a href="/client/store">{{ __('sections.nav.vps_server') }}</a>
        <a href="/client/knowledgebase">{{ __('sections.nav.knowledge_base') }}</a>
        <a href="/client/contact">{{ __('sections.nav.contact') }}</a>
        <a href="{{ route('client.login') }}">{{ __('common.actions.login') }}</a>
        <a href="{{ route('client.register') }}">{{ __('sections.nav.sign_up') }}</a>
    </div>
</nav>
