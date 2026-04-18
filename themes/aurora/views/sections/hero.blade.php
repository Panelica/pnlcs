{{-- ===== AURORA HERO SECTION ===== --}}
@php
    $c = $content ?? collect();
    $heroTitle = $c->get('title')->content_value ?? 'Illuminate Your <span>Cloud</span>';
    $heroSubtitle = $c->get('subtitle')->content_value ?? 'Next-Generation Hosting Infrastructure';
    $heroDesc = $c->get('description')->content_value ?? 'Deploy on blazing-fast isolated servers with NVMe storage, Cgroups v2 protection, and free SSL. Starting at $1.99/month.';
    $badgeText = $c->get('badge_text')->content_value ?? 'Powered by Panelica Infrastructure';
    $ctaText = $c->get('cta_text')->content_value ?? 'Get Started';
    $ctaUrl = $c->get('cta_url')->content_value ?? '/client/register';
    $stat1Icon = $c->get('stat_1_icon')->content_value ?? 'ri-shield-star-line';
    $stat1Text = $c->get('stat_1_text')->content_value ?? 'Cgroups v2 Isolation';
    $stat2Icon = $c->get('stat_2_icon')->content_value ?? 'ri-flashlight-line';
    $stat2Text = $c->get('stat_2_text')->content_value ?? 'NVMe SSD Storage';
    $callout1Icon = $c->get('callout_1_icon')->content_value ?? 'ri-gift-line';
    $callout1Text = $c->get('callout_1_text')->content_value ?? 'FREE Domain with annual plans';
    $callout2Icon = $c->get('callout_2_icon')->content_value ?? 'ri-lock-line';
    $callout2Text = $c->get('callout_2_text')->content_value ?? 'Free SSL Included';
@endphp
<section class="hero" style="padding: 110px 0 90px;">
    {{-- Aurora glow bands --}}
    <div class="aurora-glow" id="auroraGlow">
        <div class="aurora-band aurora-band--1"></div>
        <div class="aurora-band aurora-band--2"></div>
        <div class="aurora-band aurora-band--3"></div>
    </div>

    <div class="hero__orb hero__orb--1" style="background:#8b5cf6;"></div>
    <div class="hero__orb hero__orb--2" style="background:#06b6d4;"></div>
    <div class="hero__orb hero__orb--3" style="background:#34d399;"></div>

    <div class="container">
        <div class="hero__inner">
            <div>
                <div class="hero__badge"><i class="ri-sparkling-line"></i> {{ $badgeText }}</div>
                <h1 class="hero__title">{!! $heroTitle !!}</h1>
                <p class="hero__title-sub">{{ $heroSubtitle }}</p>
                <p class="hero__desc">{{ $heroDesc }}</p>
                <div class="hero__stats">
                    <div class="hero__stat"><i class="{{ $stat1Icon }}"></i> {{ $stat1Text }}</div>
                    <div class="hero__stat"><i class="{{ $stat2Icon }}"></i> {{ $stat2Text }}</div>
                </div>
                <div style="display:flex; gap:12px; align-items:center;">
                    <a href="{{ $ctaUrl }}" class="btn-accent" style="font-size: 16px; padding: 16px 36px;">
                        {{ $ctaText }} <i class="ri-arrow-right-line"></i>
                    </a>
                    <a href="/client/store" class="btn-outline" style="font-size: 14px; padding: 14px 28px; border-color: rgba(255,255,255,0.2); color:#fff;">
                        View Plans
                    </a>
                </div>
            </div>
            <div class="hero__visual" style="position:relative;">
                {{-- Aurora orb visual --}}
                <div style="width:340px;height:340px;position:relative;display:flex;align-items:center;justify-content:center;">
                    {{-- Outer ring --}}
                    <div style="position:absolute;width:340px;height:340px;border-radius:50%;border:1px solid rgba(139,92,246,0.15);animation:spin 30s linear infinite;"></div>
                    {{-- Mid ring --}}
                    <div style="position:absolute;width:260px;height:260px;border-radius:50%;border:1px solid rgba(52,211,153,0.12);animation:spin 20s linear infinite reverse;"></div>
                    {{-- Inner glow sphere --}}
                    <div style="width:160px;height:160px;border-radius:50%;background:radial-gradient(circle at 35% 35%, rgba(139,92,246,0.4), rgba(6,182,212,0.2), rgba(52,211,153,0.1));box-shadow:0 0 80px rgba(139,92,246,0.2),0 0 120px rgba(52,211,153,0.1);display:flex;align-items:center;justify-content:center;animation:auroaPulse 4s ease-in-out infinite;">
                        <i class="ri-cloud-line" style="font-size:48px;color:rgba(255,255,255,0.9);"></i>
                    </div>
                    {{-- Orbiting dots --}}
                    <div style="position:absolute;width:300px;height:300px;animation:spin 15s linear infinite;">
                        <div style="position:absolute;top:0;left:50%;width:8px;height:8px;background:#8b5cf6;border-radius:50%;box-shadow:0 0 12px #8b5cf6;transform:translateX(-50%);"></div>
                    </div>
                    <div style="position:absolute;width:240px;height:240px;animation:spin 12s linear infinite reverse;">
                        <div style="position:absolute;top:0;left:50%;width:6px;height:6px;background:#34d399;border-radius:50%;box-shadow:0 0 10px #34d399;transform:translateX(-50%);"></div>
                    </div>
                    <div style="position:absolute;width:200px;height:200px;animation:spin 18s linear infinite;">
                        <div style="position:absolute;bottom:0;left:50%;width:5px;height:5px;background:#06b6d4;border-radius:50%;box-shadow:0 0 8px #06b6d4;transform:translateX(-50%);"></div>
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
@keyframes auroaPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 80px rgba(139,92,246,0.2), 0 0 120px rgba(52,211,153,0.1); }
    50% { transform: scale(1.05); box-shadow: 0 0 100px rgba(139,92,246,0.3), 0 0 150px rgba(52,211,153,0.15); }
}
</style>

<script>
// Generate twinkling stars
(function() {
    var container = document.querySelector('.aurora-glow');
    if (!container) return;
    for (var i = 0; i < 40; i++) {
        var star = document.createElement('div');
        star.className = 'aurora-star';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.animationDelay = (Math.random() * 4) + 's';
        star.style.animationDuration = (2 + Math.random() * 4) + 's';
        var size = 1 + Math.random() * 2;
        star.style.width = size + 'px';
        star.style.height = size + 'px';
        container.appendChild(star);
    }
})();
</script>
