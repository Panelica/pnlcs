{{-- ===== FLAVOR HERO SECTION ===== --}}
@php
    $c = $content ?? collect();
    $heroTitle = $c->get('title')->content_value ?? 'Launch Your <span>Digital Presence</span>';
    $heroSubtitle = $c->get('subtitle')->content_value ?? 'High-Performance Hosting Infrastructure';
    $heroDesc = $c->get('description')->content_value ?? 'Deploy your website on blazing-fast NVMe servers with isolated resources, automatic backups, and free SSL — starting at just $1.99/month.';
    $badgeText = $c->get('badge_text')->content_value ?? 'Powered by Panelica Infrastructure';
    $ctaText = $c->get('cta_text')->content_value ?? 'Explore Plans';
    $ctaUrl = $c->get('cta_url')->content_value ?? '/client/register';
    $stat1Icon = $c->get('stat_1_icon')->content_value ?? 'ri-shield-user-line';
    $stat1Text = $c->get('stat_1_text')->content_value ?? 'Cgroups v2 Isolation';
    $stat2Icon = $c->get('stat_2_icon')->content_value ?? 'ri-speed-line';
    $stat2Text = $c->get('stat_2_text')->content_value ?? 'NVMe SSD Storage';
    $callout1Icon = $c->get('callout_1_icon')->content_value ?? 'ri-gift-line';
    $callout1Text = $c->get('callout_1_text')->content_value ?? 'FREE Domain with annual plans';
    $callout2Icon = $c->get('callout_2_icon')->content_value ?? 'ri-lock-line';
    $callout2Text = $c->get('callout_2_text')->content_value ?? 'Free SSL Included';
@endphp
<section class="hero">
    <div class="hero__orb hero__orb--1"></div>
    <div class="hero__orb hero__orb--2"></div>
    <div class="hero__orb hero__orb--3"></div>

    {{-- Flavor: Particle effect instead of globe --}}
    <div class="flavor-particles" id="flavorParticles"></div>

    <div class="container">
        <div class="hero__inner">
            <div>
                <div class="hero__badge"><i class="ri-rocket-2-line"></i> {{ $badgeText }}</div>
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
            <div class="hero__visual" style="position:relative;">
                {{-- Gradient visual with floating metrics --}}
                <div style="width:360px;height:360px;border-radius:50%;background:radial-gradient(circle at 30% 30%, rgba(249,115,22,0.2), rgba(234,88,12,0.05));position:relative;display:flex;align-items:center;justify-content:center;">
                    <div style="width:200px;height:200px;border-radius:50%;border:2px solid rgba(249,115,22,0.2);display:flex;align-items:center;justify-content:center;">
                        <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;">
                            <i class="ri-server-line" style="font-size:40px;color:#fff;"></i>
                        </div>
                    </div>
                    {{-- Orbiting dots --}}
                    <div style="position:absolute;width:100%;height:100%;animation:spin 20s linear infinite;">
                        <div style="position:absolute;top:10px;left:50%;width:8px;height:8px;background:#f97316;border-radius:50%;box-shadow:0 0 12px #f97316;"></div>
                        <div style="position:absolute;bottom:30px;right:20px;width:6px;height:6px;background:#fb923c;border-radius:50%;box-shadow:0 0 10px #fb923c;"></div>
                    </div>
                </div>
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
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
<script>
// Generate floating particles for Flavor hero
(function() {
    var container = document.getElementById('flavorParticles');
    if (!container) return;
    for (var i = 0; i < 30; i++) {
        var p = document.createElement('div');
        p.className = 'flavor-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.top = Math.random() * 100 + '%';
        p.style.animationDelay = (Math.random() * 8) + 's';
        p.style.animationDuration = (6 + Math.random() * 6) + 's';
        p.style.width = (3 + Math.random() * 4) + 'px';
        p.style.height = p.style.width;
        container.appendChild(p);
    }
})();
</script>
