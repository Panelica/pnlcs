{{-- ===== PROMO CARDS ===== --}}
@php
    $c = $content ?? collect();
    $cardsJson = $c->has('cards') ? $c->get('cards')->content_value : null;
    $cards = $cardsJson ? json_decode($cardsJson, true) : [];
@endphp
@if(count($cards))
<section class="promo-cards">
    <div class="container">
        <div class="promo-cards__grid">
            @foreach($cards as $card)
            <a href="{{ $card['cta_url'] ?? '/client/store' }}" class="promo-card {{ $card['gradient'] ?? '' }}">
                <div class="promo-card__title">{{ $card['title'] ?? '' }}</div>
                <div class="promo-card__subtitle">{{ $card['subtitle'] ?? '' }}</div>
                <div class="promo-card__prices">
                    <span class="promo-card__old">{{ $card['old_price'] ?? '' }}</span>
                    <span class="promo-card__new">{{ $card['new_price'] ?? '' }}<small>{{ $card['period'] ?? '' }}</small></span>
                </div>
                <span class="promo-card__cta">{{ $card['cta_text'] ?? 'Get Started' }} <i class="ri-arrow-right-line"></i></span>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
