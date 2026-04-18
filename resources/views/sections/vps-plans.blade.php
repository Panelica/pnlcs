{{-- ===== VPS PLANS ===== --}}
@php
    $c = $content ?? collect();
    $vpsTitle = $c->has('title') ? $c->get('title')->content_value : __('sections.vps.title');
    $vpsSubtitle = $c->has('subtitle') ? $c->get('subtitle')->content_value : __('sections.vps.subtitle');
    $visualTitle = $c->has('visual_title') ? $c->get('visual_title')->content_value : __('sections.vps.visual_title');
    $visualDesc = $c->has('visual_desc') ? $c->get('visual_desc')->content_value : '';

    $vpsProducts = isset($products) ? $products->filter(fn($p) => $p->type === 'vps' || ($p->group && str_contains(strtolower($p->group->name), 'vps'))) : collect();
    $badgeTypes = ['', 'vps-card__badge--popular', 'vps-card__badge--value', ''];
    $badgeTexts = ['', __('sections.vps.popular'), __('sections.vps.best_value'), ''];
@endphp
<section class="vps">
    <div class="container">
        <h2 class="section-title">{{ $vpsTitle }}</h2>
        <p class="section-subtitle">{{ $vpsSubtitle }}</p>
        <div class="vps__layout">
            {{-- Server rack visual --}}
            <div class="vps__visual">
                <div class="vps__server-rack">
                    <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span></div>
                    <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span></div>
                    <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--blink"></span><span class="vps__rack-slots"></span></div>
                    <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span></div>
                </div>
                <h3>{{ $visualTitle }}</h3>
                <p>{{ $visualDesc }}</p>
            </div>
            {{-- VPS cards --}}
            <div class="vps__grid">
                @forelse($vpsProducts->take(4) as $idx => $product)
                @php
                    $pricing = $product->pricing->first();
                    $monthlyPrice = $pricing ? $pricing->monthly : '0.00';
                    $configOptions = is_string($product->config_options) ? json_decode($product->config_options, true) : ($product->config_options ?? []);
                    $specs = [];
                    for ($i = 1; $i <= 5; $i++) {
                        if (!empty($configOptions["f{$i}"])) $specs[] = $configOptions["f{$i}"];
                    }
                @endphp
                <div class="vps-card">
                    @if(!empty($badgeTexts[$idx]))
                    <div class="vps-card__badge {{ $badgeTypes[$idx] ?? '' }}">{{ $badgeTexts[$idx] }}</div>
                    @endif
                    <div class="vps-card__name">{{ $product->name }}</div>
                    <div class="vps-card__specs">
                        @foreach($specs as $spec)
                        <div class="vps-card__spec"><i class="ri-check-line"></i> {{ $spec }}</div>
                        @endforeach
                    </div>
                    <div class="vps-card__price">${{ $monthlyPrice }}<small>/mo</small></div>
                    <a href="/client/store/configure/{{ $product->slug }}" class="vps-card__btn">{{ __('sections.vps.configure') }} <i class="ri-arrow-right-line"></i></a>
                </div>
                @empty
                {{-- No VPS products --}}
                @endforelse
            </div>
        </div>
    </div>
</section>
