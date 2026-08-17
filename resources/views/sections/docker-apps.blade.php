{{-- ===== ONE-CLICK APPS =====
     The shop window for the app catalogue, and the one part of the offer that
     competitors cannot match on price alone: apps run inside the customer's own
     cgroup slice, with no privileged containers and no Docker-in-Docker, so one
     tenant cannot eat another's resources.

     Everything here - heading, subheading, the three points, how many apps, the
     buttons - is editable from the admin Homepage screen. Draws nothing at all
     when the catalogue cannot be read, rather than an empty section. --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => $c->has($key) ? ($c->get($key)->content_value ?: $fallback) : $fallback;

    $limit = (int) $val('limit', 24);
    $all = $apps ?? [];
    $shown = array_slice($all, 0, $limit > 0 ? $limit : 24);
    $total = count($all);
@endphp
@if(count($shown))
<section class="oca" id="apps">
    <div class="container">

        <div class="oca__top">
            <div class="oca__intro">
                <span class="oca__eyebrow">{{ $val('eyebrow', __('sections.apps.eyebrow')) }}</span>
                <h2 class="oca__title">{{ $val('title', __('sections.apps.title')) }}</h2>
                <p class="oca__sub">{{ $val('subtitle', __('sections.apps.subtitle', ['count' => $total])) }}</p>

                <ul class="oca__points">
                    <li><i class="ri-shield-check-line"></i><span><strong>{{ __('sections.apps.point_isolation_t') }}</strong>{{ $val('point_1', __('sections.apps.point_isolation')) }}</span></li>
                    <li><i class="ri-flashlight-line"></i><span><strong>{{ __('sections.apps.point_oneclick_t') }}</strong>{{ $val('point_2', __('sections.apps.point_oneclick')) }}</span></li>
                    <li><i class="ri-price-tag-3-line"></i><span><strong>{{ __('sections.apps.point_included_t') }}</strong>{{ $val('point_3', __('sections.apps.point_included')) }}</span></li>
                </ul>

                <div class="oca__cta-row">
                    <a href="{{ $val('cta_url', route('client.store')) }}" class="oca__cta">
                        {{ $val('cta_text', __('sections.apps.cta')) }} <i class="ri-arrow-right-line"></i>
                    </a>
                    <a href="{{ $val('cta2_url', route('client.kb.index')) }}" class="oca__cta oca__cta--ghost">
                        {{ $val('cta2_text', __('sections.apps.cta_learn')) }}
                    </a>
                </div>
            </div>

            <div class="oca__gridwrap">
                <div class="oca__grid">
                    @foreach($shown as $a)
                    @php
                        $hue = crc32($a['slug']) % 360;
                        $initial = mb_strtoupper(mb_substr(trim($a['name']) ?: $a['slug'], 0, 1));
                        $line = $a['tagline'] ?: $a['description'];
                    @endphp
                    <div class="oca__app" title="{{ $line }}">
                        @if($a['is_featured'])<span class="oca__star" title="{{ __('sections.apps.featured') }}">&#9733;</span>@endif
                        @if($a['logo_url_local'])
                            <img src="{{ $a['logo_url_local'] }}" alt="" loading="lazy" class="oca__logo">
                        @else
                            <div class="oca__mark" style="background:hsl({{ $hue }},62%,94%);color:hsl({{ $hue }},52%,34%);border-color:hsl({{ $hue }},45%,84%)">{{ $initial }}</div>
                        @endif
                        <div class="oca__nm">{{ $a['name'] }}</div>
                    </div>
                    @endforeach
                </div>
                @if($total > count($shown))
                <div class="oca__more">{{ __('sections.apps.and_more', ['count' => $total - count($shown)]) }}</div>
                @endif
            </div>
        </div>

        {{-- Three steps, because "one click" is a claim and this is the proof. --}}
        <div class="oca__how">
            <div class="oca__step"><span class="oca__num">1</span><div><strong>{{ __('sections.apps.step1_t') }}</strong><p>{{ __('sections.apps.step1') }}</p></div></div>
            <div class="oca__step"><span class="oca__num">2</span><div><strong>{{ __('sections.apps.step2_t') }}</strong><p>{{ __('sections.apps.step2') }}</p></div></div>
            <div class="oca__step"><span class="oca__num">3</span><div><strong>{{ __('sections.apps.step3_t') }}</strong><p>{{ __('sections.apps.step3') }}</p></div></div>
        </div>
    </div>
</section>

<style>
    .oca{padding:78px 0;position:relative}
    .oca__top{display:grid;grid-template-columns:minmax(300px,1fr) minmax(320px,1.25fr);gap:48px;align-items:start}
    .oca__eyebrow{display:inline-block;font-size:11.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
        opacity:.6;margin-bottom:12px}
    .oca__title{font-size:clamp(28px,4vw,42px);font-weight:800;margin:0 0 14px;line-height:1.15;letter-spacing:-.5px}
    .oca__sub{font-size:16px;opacity:.75;margin:0 0 26px;line-height:1.6}
    .oca__points{list-style:none;margin:0 0 30px;padding:0;display:flex;flex-direction:column;gap:16px}
    .oca__points li{display:flex;gap:13px;align-items:flex-start;font-size:14px;line-height:1.55}
    .oca__points i{font-size:20px;flex:0 0 auto;margin-top:1px;opacity:.85}
    .oca__points strong{display:block;font-weight:800;margin-bottom:2px}
    .oca__points span{opacity:.78}
    .oca__points strong{opacity:1}
    .oca__cta-row{display:flex;gap:12px;flex-wrap:wrap}
    .oca__cta{display:inline-flex;align-items:center;gap:8px;font-size:14.5px;font-weight:700;text-decoration:none;
        padding:13px 26px;border-radius:999px;border:1px solid currentColor;transition:transform .14s}
    .oca__cta:hover{transform:translateY(-2px)}
    .oca__cta--ghost{opacity:.7;font-weight:600}
    .oca__gridwrap{position:relative}
    .oca__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:12px}
    .oca__app{position:relative;border:1px solid rgba(127,127,127,.22);border-radius:14px;padding:16px 8px;
        text-align:center;transition:transform .16s,border-color .16s,box-shadow .16s;background:rgba(127,127,127,.03)}
    .oca__app:hover{transform:translateY(-4px);border-color:currentColor;box-shadow:0 10px 26px rgba(0,0,0,.08)}
    .oca__logo,.oca__mark{width:38px;height:38px;margin:0 auto 9px;display:block;object-fit:contain}
    .oca__mark{border-radius:10px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800}
    .oca__nm{font-size:11.5px;font-weight:700;line-height:1.25;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .oca__star{position:absolute;top:6px;right:7px;color:#f0a92b;font-size:10px;line-height:1}
    .oca__more{text-align:center;font-size:13px;opacity:.6;margin-top:16px}
    .oca__how{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:22px;margin-top:52px;
        padding-top:38px;border-top:1px solid rgba(127,127,127,.18)}
    .oca__step{display:flex;gap:14px;align-items:flex-start}
    .oca__num{flex:0 0 auto;width:30px;height:30px;border-radius:50%;border:1px solid currentColor;opacity:.55;
        display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
    .oca__step strong{display:block;font-size:14px;font-weight:800;margin-bottom:5px}
    .oca__step p{margin:0;font-size:13px;opacity:.72;line-height:1.55}
    @media (max-width:900px){
        .oca{padding:56px 0}
        .oca__top{grid-template-columns:1fr;gap:34px}
    }
</style>
@endif
