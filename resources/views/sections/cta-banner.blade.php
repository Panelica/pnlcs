{{-- ===== CTA BANNER ===== --}}
@php
    $c = $content ?? collect();
    $ctaTitle = $c->has('title') ? $c->get('title')->content_value : 'Ready to Get Started?';
    $ctaSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : 'Join thousands of satisfied customers.';
    $ctaText = $c->has('cta_text') ? $c->get('cta_text')->content_value : 'Start Your Journey';
    $ctaUrl = $c->has('cta_url') ? $c->get('cta_url')->content_value : '/client/register';
    $noteText = $c->has('note_text') ? $c->get('note_text')->content_value : 'No credit card required. 30-day money-back guarantee.';
@endphp
<section class="cta-banner">
    <div class="container">
        <div class="cta-banner__inner">
            <h2 class="cta-banner__title">{{ $ctaTitle }}</h2>
            <p class="cta-banner__desc">{{ $ctaSubtitle }}</p>
            <a href="{{ $ctaUrl }}" class="btn-accent" style="font-size: 16px; padding: 16px 36px;">
                {{ $ctaText }} <i class="ri-arrow-right-line"></i>
            </a>
            <p class="cta-banner__note">{{ $noteText }}</p>
        </div>
    </div>
</section>
