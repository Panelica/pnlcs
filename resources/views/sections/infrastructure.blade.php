{{-- ===== INFRASTRUCTURE ===== --}}
@php
    $c = $content ?? collect();
    $infraTitle = $c->has('title') ? $c->get('title')->content_value : __('sections.infra.title');
    $infraSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : __('sections.infra.subtitle');
    $cardsJson = $c->has('cards') ? $c->get('cards')->content_value : null;
    $cards = $cardsJson ? json_decode($cardsJson, true) : [];
@endphp
<section class="infra">
    <div class="container">
        <h2 class="section-title">{{ $infraTitle }}</h2>
        <p class="section-subtitle">{{ $infraSubtitle }}</p>
        <div class="infra__grid">
            @foreach($cards as $card)
            <div class="infra-card">
                <div class="infra-card__icon {{ $card['icon_class'] ?? '' }}">
                    <i class="{{ $card['icon'] ?? 'ri-server-line' }}"></i>
                </div>
                <div class="infra-card__title">{{ $card['title'] ?? '' }}</div>
                <div class="infra-card__desc">{{ $card['desc'] ?? '' }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
