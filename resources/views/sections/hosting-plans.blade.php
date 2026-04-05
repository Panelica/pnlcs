{{-- ===== HOSTING PLANS ===== --}}
@php
    $c = $content ?? collect();
    $hpTitle = $c->has('title') ? $c->get('title')->content_value : 'Popular Web Hosting Plans';
    $hpSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : 'Choose the perfect plan for your website.';
    $promoIcon = $c->has('promo_icon') ? $c->get('promo_icon')->content_value : 'ri-gift-2-line';
    $promoTitle = $c->has('promo_title') ? $c->get('promo_title')->content_value : 'FREE .COM Domain with Annual Plans';
    $promoText = $c->has('promo_text') ? $c->get('promo_text')->content_value : 'Get a free domain registration when you sign up for any annual hosting plan.';
    $promoCta = $c->has('promo_cta') ? $c->get('promo_cta')->content_value : 'Claim Offer';

    $hostingProducts = isset($products) ? $products->filter(fn($p) => $p->type === 'hosting') : collect();
    $icons = ['ri-rocket-line', 'ri-speed-line', 'ri-flashlight-line', 'ri-vip-crown-line'];
@endphp
<section class="hosting-plans">
    <div class="container">
        <h2 class="section-title">{{ $hpTitle }}</h2>
        <p class="section-subtitle">{{ $hpSubtitle }}</p>
        <div class="hosting-plans__grid">
            {{-- Promo sidebar --}}
            <div class="hosting-plans__promo">
                <div class="hosting-plans__promo-icon"><i class="{{ $promoIcon }}"></i></div>
                <h3>{{ $promoTitle }}</h3>
                <p>{{ $promoText }}</p>
                <a href="/client/store" class="hosting-plans__promo-btn">{{ $promoCta }} <i class="ri-arrow-right-line"></i></a>
            </div>
            {{-- Plan cards --}}
            @foreach($hostingProducts->take(3) as $idx => $product)
            @php
                $pricing = $product->pricing->first();
                $monthlyPrice = $pricing ? $pricing->monthly : '0.00';
                $annualPrice = $pricing ? $pricing->annually : '0.00';
                $configOptions = is_string($product->config_options) ? json_decode($product->config_options, true) : ($product->config_options ?? []);
                $features = [];
                for ($i = 1; $i <= 7; $i++) {
                    if (!empty($configOptions["f{$i}"])) $features[] = $configOptions["f{$i}"];
                }
                $isPopular = $idx === 1;
            @endphp
            <div class="plan-card {{ $isPopular ? 'plan-card--popular' : '' }}">
                @if($isPopular)
                <div class="plan-card__badge">Most Popular</div>
                @endif
                <div class="plan-card__icon"><i class="{{ $icons[$idx] ?? 'ri-server-line' }}"></i></div>
                <div class="plan-card__name">{{ $product->name }}</div>
                <div class="plan-card__subtitle">{{ $product->description }}</div>
                <div class="plan-card__pricing">
                    <div class="plan-card__price">${{ $monthlyPrice }}<small>/mo</small></div>
                </div>
                <div class="plan-card__features">
                    @foreach($features as $feature)
                    <div class="plan-card__feature">
                        <i class="ri-check-line"></i>
                        <span>{{ $feature }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="plan-card__cp"><i class="ri-dashboard-line"></i> Panelica Control Panel</div>
                <a href="/client/store/configure/{{ $product->slug }}" class="plan-card__btn {{ $isPopular ? 'plan-card__btn--primary' : 'plan-card__btn--outline' }}">
                    Get Started <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
