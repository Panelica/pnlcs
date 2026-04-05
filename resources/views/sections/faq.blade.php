{{-- ===== FAQ ===== --}}
@php
    $c = $content ?? collect();
    $faqTitle = $c->has('title') ? $c->get('title')->content_value : __('sections.faq.title');
    $faqSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : __('sections.faq.subtitle');
    $itemsJson = $c->has('items') ? $c->get('items')->content_value : null;
    $items = $itemsJson ? json_decode($itemsJson, true) : [];
@endphp
@if(count($items))
<section class="faq">
    <div class="container">
        <h2 class="section-title">{{ $faqTitle }}</h2>
        <p class="section-subtitle">{{ $faqSubtitle }}</p>
        <div class="faq__list">
            @foreach($items as $idx => $item)
            <div class="faq__item" x-data="{ open: {{ $idx === 0 ? 'true' : 'false' }} }">
                <button class="faq__question" @click="open = !open">
                    <span>{{ $item['question'] ?? '' }}</span>
                    <i class="ri-arrow-down-s-line" :style="open && 'transform: rotate(180deg)'"></i>
                </button>
                <div class="faq__answer" x-show="open" x-collapse>
                    {{ $item['answer'] ?? '' }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
