{{-- ===== HERO SECTION ===== --}}
@php
    $c = $content ?? collect();
    $heroTitle = $c->get('title')->content_value ?? 'Hosting That <span>Simply Works</span>';
    $heroSubtitle = $c->get('subtitle')->content_value ?? 'Panelica-Powered Isolated Infrastructure';
    $heroDesc = $c->get('description')->content_value ?? 'Launch your website on Panelica\'s isolated hosting platform with NVMe storage, per-account resource limits, and free SSL — from $1.99/month.';
    $badgeText = $c->get('badge_text')->content_value ?? 'Powered by Panelica Infrastructure';
    $ctaText = $c->get('cta_text')->content_value ?? 'Get Started Now';
    $ctaUrl = $c->get('cta_url')->content_value ?? '/client/register';
    $stat1Icon = $c->get('stat_1_icon')->content_value ?? 'ri-shield-user-line';
    $stat1Text = $c->get('stat_1_text')->content_value ?? 'Cgroups v2 Isolation';
    $stat2Icon = $c->get('stat_2_icon')->content_value ?? 'ri-speed-line';
    $stat2Text = $c->get('stat_2_text')->content_value ?? 'Nginx + PHP-FPM';
    $callout1Icon = $c->get('callout_1_icon')->content_value ?? 'ri-gift-line';
    $callout1Text = $c->get('callout_1_text')->content_value ?? 'FREE Domain with annual plans';
    $callout2Icon = $c->get('callout_2_icon')->content_value ?? 'ri-lock-line';
    $callout2Text = $c->get('callout_2_text')->content_value ?? 'Free SSL Included';
@endphp
<section class="hero">
    <div class="hero__orb hero__orb--1"></div>
    <div class="hero__orb hero__orb--2"></div>
    <div class="hero__orb hero__orb--3"></div>
    <div class="container">
        <div class="hero__inner">
            <div>
                <div class="hero__badge"><i class="ri-shield-check-line"></i> {{ $badgeText }}</div>
                <h1 class="hero__title">{!! $heroTitle !!}</h1>
                <p class="hero__title-sub">{{ $heroSubtitle }}</p>
                <p class="hero__desc">{{ $heroDesc }}</p>
                <div class="hero__stats">
                    <div class="hero__stat"><i class="{{ $stat1Icon }}"></i> {{ $stat1Text }}</div>
                    <div class="hero__stat"><i class="{{ $stat2Icon }}"></i> {{ $stat2Text }}</div>
                </div>
                <a href="{{ $ctaUrl }}" class="btn-accent" style="font-size: 16px; padding: 16px 36px;">
                    {{ $ctaText }} <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            <div class="hero__visual">
                <canvas id="heroCanvas" style="width:100%;max-width:480px;height:400px;"></canvas>
                <div class="hero__callout hero__callout--1">
                    <i class="{{ $callout1Icon }}"></i> {{ $callout1Text }}
                </div>
                <div class="hero__callout hero__callout--2">
                    <i class="{{ $callout2Icon }}"></i> {{ $callout2Text }}
                </div>
            </div>
        </div>
    </div>
</section>
