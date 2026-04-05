{{-- ===== STATS COUNTER ===== --}}
@php
    $c = $content ?? collect();
    $itemsJson = $c->has('items') ? $c->get('items')->content_value : null;
    $statItems = $itemsJson ? json_decode($itemsJson, true) : null;

    if (!$statItems) {
        $statItems = [
            ['number' => '5', 'suffix' => '+', 'label' => __('sections.stats.php_versions')],
            ['number' => '20', 'suffix' => '+', 'label' => __('sections.stats.managed_services')],
            ['number' => '24', 'suffix' => '/7', 'label' => __('sections.stats.monitoring')],
            ['number' => '100', 'suffix' => '%', 'label' => __('sections.stats.isolation')],
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
