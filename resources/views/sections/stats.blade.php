{{-- ===== STATS COUNTER ===== --}}
@php
    $c = $content ?? collect();
    $itemsJson = $c->has('items') ? $c->get('items')->content_value : null;
    $statItems = $itemsJson ? json_decode($itemsJson, true) : null;

    if (!$statItems) {
        $statItems = [
            ['number' => '5', 'suffix' => '+', 'label' => 'PHP Versions'],
            ['number' => '20', 'suffix' => '+', 'label' => 'Managed Services'],
            ['number' => '24', 'suffix' => '/7', 'label' => 'Monitoring & Alerts'],
            ['number' => '100', 'suffix' => '%', 'label' => 'Resource Isolation'],
        ];
    }
@endphp
<section class="stats">
    <div class="container">
        <div class="stats__grid">
            @foreach($statItems as $stat)
            <div class="stats__item">
                <div class="stats__number">{{ $stat['number'] }}<span>{{ $stat['suffix'] }}</span></div>
                <div class="stats__label">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
