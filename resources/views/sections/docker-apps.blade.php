{{-- ===== ONE-CLICK APPS =====
     The shop window for the app catalogue. Everything shown here - the heading,
     the line under it, how many apps, the button - is editable from the admin
     Homepage screen, and the apps themselves come from what the operator marked
     as offered, arranged the way the order form arranges them. Draws nothing at
     all when the catalogue cannot be read, rather than an empty section. --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => $c->has($key) ? ($c->get($key)->content_value ?: $fallback) : $fallback;

    $limit = (int) $val('limit', 12);
    $shown = array_slice($apps ?? [], 0, $limit > 0 ? $limit : 12);
    $total = count($apps ?? []);
@endphp
@if(count($shown))
<section class="oca" id="apps">
    <div class="container">
        <div class="oca__head">
            <h2 class="oca__title">{{ $val('title', __('sections.apps.title')) }}</h2>
            <p class="oca__sub">{{ $val('subtitle', __('sections.apps.subtitle', ['count' => $total])) }}</p>
        </div>

        <div class="oca__grid">
            @foreach($shown as $a)
            @php
                $hue = crc32($a['slug']) % 360;
                $initial = mb_strtoupper(mb_substr(trim($a['name']) ?: $a['slug'], 0, 1));
                $line = $a['tagline'] ?: $a['description'];
            @endphp
            <div class="oca__app" title="{{ $line }}">
                @if($a['is_featured'])<span class="oca__star">&#9733;</span>@endif
                @if($a['logo_url_local'])
                    <img src="{{ $a['logo_url_local'] }}" alt="" loading="lazy" class="oca__logo">
                @else
                    <div class="oca__mark" style="background:hsl({{ $hue }},62%,94%);color:hsl({{ $hue }},52%,34%);border-color:hsl({{ $hue }},45%,84%)">{{ $initial }}</div>
                @endif
                <div class="oca__nm">{{ $a['name'] }}</div>
                @if($line)<div class="oca__ds">{{ $line }}</div>@endif
            </div>
            @endforeach
        </div>

        <div class="oca__foot">
            @if($total > count($shown))
            <span class="oca__more">{{ __('sections.apps.and_more', ['count' => $total - count($shown)]) }}</span>
            @endif
            <a href="{{ $val('cta_url', route('client.store')) }}" class="oca__cta">
                {{ $val('cta_text', __('sections.apps.cta')) }} <i class="ri-arrow-right-line"></i>
            </a>
        </div>

        {{-- The part competitors cannot say. Every app runs inside the
             customer's own cgroup slice, with no privileged containers and no
             Docker-in-Docker, so one tenant cannot eat another's resources. --}}
        <div class="oca__points">
            <div class="oca__point"><i class="ri-shield-check-line"></i>{{ $val('point_1', __('sections.apps.point_isolation')) }}</div>
            <div class="oca__point"><i class="ri-flashlight-line"></i>{{ $val('point_2', __('sections.apps.point_oneclick')) }}</div>
            <div class="oca__point"><i class="ri-price-tag-3-line"></i>{{ $val('point_3', __('sections.apps.point_included')) }}</div>
        </div>
    </div>
</section>

<style>
    .oca{padding:64px 0}
    .oca__head{text-align:center;max-width:640px;margin:0 auto 32px}
    .oca__title{font-size:clamp(24px,3.4vw,34px);font-weight:800;margin:0 0 10px}
    .oca__sub{font-size:15px;opacity:.72;margin:0;line-height:1.55}
    .oca__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(132px,1fr));gap:14px}
    .oca__app{position:relative;border:1px solid rgba(127,127,127,.22);border-radius:14px;padding:18px 12px;
        text-align:center;transition:transform .16s,border-color .16s,box-shadow .16s;background:rgba(127,127,127,.03)}
    .oca__app:hover{transform:translateY(-4px);border-color:currentColor;box-shadow:0 8px 24px rgba(0,0,0,.07)}
    .oca__logo,.oca__mark{width:42px;height:42px;margin:0 auto 10px;display:block;object-fit:contain}
    .oca__mark{border-radius:11px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800}
    .oca__nm{font-size:13px;font-weight:700;line-height:1.25}
    .oca__ds{font-size:11px;opacity:.62;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .oca__star{position:absolute;top:8px;right:9px;color:#f0a92b;font-size:11px;line-height:1}
    .oca__foot{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:26px}
    .oca__more{font-size:13px;opacity:.65}
    .oca__cta{display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:700;text-decoration:none;
        padding:11px 22px;border-radius:999px;border:1px solid currentColor}
    .oca__points{display:flex;justify-content:center;gap:26px;flex-wrap:wrap;margin-top:30px}
    .oca__point{display:inline-flex;align-items:center;gap:7px;font-size:13px;opacity:.75}
    .oca__point i{font-size:16px}
</style>
@endif
