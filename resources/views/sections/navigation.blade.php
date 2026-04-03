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
                    <span class="main-nav__link">Domains <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__dropdown">
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-search-line"></i> Domain Search</a>
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-exchange-line"></i> Domain Transfer</a>
                        <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-file-search-line"></i> WHOIS Lookup</a>
                    </div>
                </div>
                <div class="main-nav__item">
                    <span class="main-nav__link">Hosting <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__mega" style="min-width: 480px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> Shared Hosting</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-wordpress-line"></i> WordPress Hosting</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-building-line"></i> Business Hosting</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-group-line"></i> Reseller Hosting</a>
                        </div>
                        <div class="main-nav__mega-promo">
                            <span class="main-nav__mega-promo-text"><i class="ri-gift-2-line"></i> Get 50% off your first hosting plan!</span>
                            <a href="{{ route('client.register') }}" class="main-nav__mega-promo-btn">Claim Now</a>
                        </div>
                    </div>
                </div>
                <div class="main-nav__item">
                    <span class="main-nav__link">Servers <i class="ri-arrow-down-s-line"></i></span>
                    <div class="main-nav__dropdown">
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-cloud-line"></i> VPS Server</a>
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-hard-drive-2-line"></i> VDS Server</a>
                        <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> Dedicated Server</a>
                    </div>
                </div>
                <div class="main-nav__item">
                    <a href="/client/knowledgebase" class="main-nav__link">Knowledge Base</a>
                </div>
                <div class="main-nav__item">
                    <a href="/client/store" class="main-nav__link">Store</a>
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
                <a href="{{ route('client.login') }}" class="main-nav__login">Login</a>
                <button class="main-nav__hamburger" @click="mobileMenu = !mobileMenu"><i class="ri-menu-line"></i></button>
            </div>
        </div>
    </div>

    <div class="main-nav__mobile-menu" x-show="mobileMenu" x-transition @click.away="mobileMenu = false" style="display: none;">
        <a href="/client/domain-search">Domain Search</a>
        <a href="/client/store">Hosting Plans</a>
        <a href="/client/store">VPS Server</a>
        <a href="/client/knowledgebase">Knowledge Base</a>
        <a href="/client/contact">Contact</a>
        <a href="{{ route('client.login') }}">Login</a>
        <a href="{{ route('client.register') }}">Sign Up</a>
    </div>
</nav>
