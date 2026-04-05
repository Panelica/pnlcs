{{-- ===== TESTIMONIALS ===== --}}
@php
    $c = $content ?? collect();
    $testTitle = $c->has('title') ? $c->get('title')->content_value : 'What Our Customers Say';
    $testSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : 'Trusted by thousands of businesses worldwide';
    $itemsJson = $c->has('items') ? $c->get('items')->content_value : null;
    $items = $itemsJson ? json_decode($itemsJson, true) : [];
@endphp
@if(count($items))
<section class="testimonials">
    <div class="container">
        <h2 class="section-title">{{ $testTitle }}</h2>
        <p class="section-subtitle">{{ $testSubtitle }}</p>
        <div class="testimonials__grid">
            @foreach($items as $item)
            <div class="testimonial-card">
                <div class="testimonial-card__stars">
                    <i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i>
                </div>
                <div class="testimonial-card__text">"{{ $item['text'] ?? '' }}"</div>
                <div class="testimonial-card__author">
                    <div class="testimonial-card__avatar {{ $item['avatar_class'] ?? '' }}">{{ $item['initials'] ?? '' }}</div>
                    <div>
                        <div class="testimonial-card__name">{{ $item['name'] ?? '' }}</div>
                        <div class="testimonial-card__role">{{ $item['role'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
