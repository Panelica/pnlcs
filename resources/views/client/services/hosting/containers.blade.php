@extends("client.layouts.app")
@section("title", __('client.hosting.containers.title'))
@section("content")

<style>
    .ct-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .ct-back:hover{color:var(--primary)}
    .ct-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .ct-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(14,165,233,.6)}
    .ct-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .ct-head .sub{font-size:13px;color:var(--muted)}
    .ct-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    /* One column, the customer's own apps first, the catalogue below as an
       accordion. Ninety-eight apps left open ran the page to 15,000px; half
       open shows there is more and that it folds away. */
    .ct-split{display:flex;flex-direction:column}
    .ct-col-main{order:1;min-width:0}
    .ct-col-side{order:2;min-width:0;position:relative;overflow:hidden;
        max-height:var(--ct-peek,560px);transition:max-height .25s ease}
    .ct-col-side.is-open{max-height:none}
    .ct-col-side .ct-ch{cursor:pointer;user-select:none}
    .ct-col-side .ct-ch .ct-chev{margin-left:auto;font-size:16px;color:var(--muted);
        transition:transform .25s ease}
    .ct-col-side.is-open .ct-ch .ct-chev{transform:rotate(180deg)}
    .ct-fade{position:absolute;left:0;right:0;bottom:0;height:120px;pointer-events:none;
        background:linear-gradient(to bottom,rgba(0,0,0,0),var(--card) 78%)}
    .ct-col-side.is-open .ct-fade{display:none}
    .ct-more{position:absolute;left:0;right:0;bottom:14px;display:flex;justify-content:center}
    .ct-more button{pointer-events:auto;border:1px solid var(--border);background:var(--card);
        color:var(--primary);font-size:12.5px;font-weight:700;padding:8px 18px;border-radius:999px;
        cursor:pointer;box-shadow:var(--shadow);display:inline-flex;align-items:center;gap:6px}
    .ct-more button:hover{border-color:var(--primary)}
    .ct-col-side.is-open .ct-more{position:static;margin:0 0 16px}
    .ct-open{margin-top:7px;display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700}
    .ct-open a{color:var(--primary);text-decoration:none}
    .ct-open a:hover{text-decoration:underline}
    .ct-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .ct-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .ct-note{padding:16px 18px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--muted);line-height:1.5}
    .ct-note i{font-size:18px;color:#f59e0b;flex-shrink:0}
    .ct-note.info i{color:var(--primary)}
    .ct-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}

    /* App catalogue */
    .ct-apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;padding:18px}
    .ct-app{border:1px solid var(--border);border-radius:12px;padding:14px;text-align:center;cursor:pointer;background:var(--bg);transition:transform .14s,border-color .14s}
    .ct-app:hover{transform:translateY(-3px);border-color:var(--primary)}
    .ct-app.on{border-color:var(--primary);background:var(--primary-light)}
    /* Same footprint as the letter tile, so a card keeps its shape whether or
       not an operator has set an image for that app. */
    .ct-logo{width:42px;height:42px;margin:0 auto 9px;display:block;object-fit:contain}
    .ct-mark{width:42px;height:42px;margin:0 auto 9px;border-radius:11px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;letter-spacing:-.5px}
    .ct-search{position:relative;display:flex;align-items:center;gap:8px;padding:16px 18px 0}
    .ct-search > i{position:absolute;left:30px;color:var(--muted);font-size:15px;pointer-events:none}
    .ct-search .ct-inp{flex:1;padding-left:34px}
    .ct-clear{position:absolute;right:120px;background:none;border:0;color:var(--muted);cursor:pointer;font-size:15px;line-height:1;padding:4px}
    .ct-clear:hover{color:var(--text)}
    .ct-count{font-size:11.5px;color:var(--muted);white-space:nowrap;min-width:78px;text-align:right}
    .ct-noresult{padding:22px 18px;text-align:center;color:var(--muted);font-size:13px}
    .ct-group{padding-top:6px}
    .ct-gh{display:flex;align-items:baseline;gap:9px;flex-wrap:wrap;padding:16px 18px 0}
    .ct-gt{font-size:13px;font-weight:800;color:var(--text)}
    .ct-gd{font-size:11.5px;color:var(--muted)}
    .ct-app{position:relative}
    .ct-pop{position:absolute;top:7px;right:8px;color:#f0a92b;font-size:11px;line-height:1}
    .ct-req{display:flex;justify-content:center;flex-wrap:wrap;gap:5px;margin-top:7px}
    .ct-req-i{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;color:var(--muted);
        background:var(--bg-alt,rgba(127,127,127,.09));border-radius:5px;padding:2px 5px;line-height:1.4}
    .ct-req-i.over{color:#b3261e;background:rgba(179,38,30,.1)}
    .ct-req-i.light{font-weight:500}
    .ct-app.big{opacity:.72}
    .ct-over{margin-top:6px;font-size:10px;font-weight:700;color:#b3261e}
    .ct-pick{margin-top:9px;width:100%;border:1px solid var(--border);background:var(--bg);color:var(--text);
        border-radius:8px;padding:5px 8px;font-size:11px;font-weight:700;cursor:pointer;
        display:inline-flex;align-items:center;justify-content:center;gap:4px;opacity:0;transition:opacity .14s}
    .ct-app:hover .ct-pick,.ct-app.on .ct-pick,.ct-app:focus-within .ct-pick{opacity:1}
    .ct-app.on .ct-pick{border-color:var(--primary);color:var(--primary)}
    /* Sits in the grid as its own full-width row, directly under the chosen card. */
    .ct-form{grid-column:1/-1}
    .ct-apps .ct-form{margin:2px 0 6px;padding:12px 14px;border:1px solid var(--primary);border-radius:10px;
        background:var(--primary-light);align-items:flex-end}
    .ct-chosen{flex:0 0 auto;align-self:center;font-size:12.5px;color:var(--muted);padding-right:4px}
    .ct-chosen-n{font-weight:800;color:var(--text)}
    .ct-cancel{background:none;border:0;color:var(--muted);font-size:12px;cursor:pointer;padding:8px 4px}
    .ct-cancel:hover{color:var(--text);text-decoration:underline}
    .ct-app .nm{font-size:12.5px;font-weight:700;color:var(--text);line-height:1.25}
    .ct-app .ds{font-size:10.5px;color:var(--muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ct-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:0 18px 18px}
    .ct-fld{flex:1;min-width:150px}
    .ct-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .ct-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px;box-sizing:border-box}
    .ct-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .ct-btn:hover{background:var(--primary-dark)}
    .ct-btn:disabled{opacity:.5;cursor:not-allowed}

    /* Running list */
    .ct-table{width:100%;border-collapse:collapse}
    .ct-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .ct-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)}
    .ct-table tbody tr:last-child td{border-bottom:none}
    .ct-name{font-weight:700}
    .ct-img{font-size:11.5px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .ct-run{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669}
    .ct-stop{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted)}
    .ct-meter{font-size:11.5px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .ct-bar{height:5px;border-radius:999px;background:var(--border);overflow:hidden;margin-top:4px;width:90px}
    .ct-bar span{display:block;height:100%;background:var(--primary)}
    .ct-acts{display:inline-flex;gap:6px;justify-content:flex-end}
    .ct-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);color:var(--muted);cursor:pointer;background:transparent}
    .ct-act:hover{background:var(--primary-light);color:var(--primary);border-color:var(--primary)}
    .ct-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .ct-spin{animation:ct-rot 1s linear infinite;display:inline-block}
    @keyframes ct-rot{to{transform:rotate(360deg)}}
    .ct-installnote{flex-basis:100%;font-size:11.5px;color:var(--muted);margin-top:8px;line-height:1.45}
    .ct-warn{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(245,158,11,.14);color:#b45309}
    .ct-hint{font-size:10.5px;color:var(--muted);margin-top:4px;line-height:1.4;max-width:230px}
    /* One card per app. The table put the name, the address, the credentials and
       the domain picker all in the first cell and left the other four columns
       vertically centred against it - a row half a screen tall with its status
       floating in the middle of the whitespace. */
    .ct-list{padding:14px 16px;display:flex;flex-direction:column;gap:14px}
    .ct-item{border:1px solid var(--border);border-radius:12px;background:var(--card);overflow:hidden}
    .ct-it-head{display:flex;align-items:center;gap:12px;padding:13px 15px;border-bottom:1px solid var(--border)}
    .ct-it-logo,.ct-it-mark{width:34px;height:34px;border-radius:9px;flex:0 0 auto;object-fit:contain}
    .ct-it-mark{background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;
        justify-content:center;font-weight:800;font-size:15px;color:var(--muted)}
    .ct-it-id{min-width:0;flex:1 1 auto}
    .ct-it-state{flex:0 0 auto}
    .ct-it-hint{padding:0 15px;margin-top:10px}
    .ct-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:var(--border)}
    .ct-fact{background:var(--card);padding:11px 15px;min-width:0}
    .ct-fact > span{display:block;font-size:10.5px;font-weight:700;letter-spacing:.4px;
        text-transform:uppercase;color:var(--muted);margin-bottom:5px}
    .ct-fact > b{display:flex;flex-wrap:wrap;align-items:center;gap:5px;font-size:12.5px;
        font-weight:600;color:var(--text);min-height:26px}
    .ct-fact a{color:var(--primary);text-decoration:none;word-break:break-all}
    .ct-fact .ct-hint{margin:0;font-weight:500}
    .ct-it-foot{display:flex;flex-direction:column;gap:9px;padding:13px 15px;border-top:1px solid var(--border);background:var(--bg)}
    .ct-go{align-self:flex-start;display:inline-flex;align-items:center;gap:6px;padding:8px 15px;
        border-radius:9px;background:var(--primary);color:#fff;font-size:12.5px;font-weight:700;text-decoration:none}
    .ct-go:hover{filter:brightness(1.07)}
    .ct-acc{font-size:12px}
    .ct-acc summary{cursor:pointer;color:var(--primary);font-weight:700;list-style:none;display:inline-flex;align-items:center;gap:5px}
    .ct-acc summary::-webkit-details-marker{display:none}
    .ct-acc summary::before{content:'\25b8';font-size:10px}
    .ct-acc[open] summary::before{content:'\25be'}
    /* Labels in their own column so the values line up instead of stepping in
       and out with the length of each name. */
    .ct-acc-b{margin-top:9px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card)}
    .ct-acc-r{display:grid;grid-template-columns:minmax(120px,190px) minmax(0,1fr) auto;
        gap:10px;align-items:center;padding:5px 0;border-bottom:1px solid var(--border)}
    .ct-acc-r:last-of-type{border-bottom:0}
    .ct-acc-r > span{color:var(--muted);font-size:11px;font-weight:600;word-break:break-word}
    .ct-acc-r code{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;word-break:break-all;
        background:var(--bg);border-radius:5px;padding:3px 7px;color:var(--text)}
    .ct-acc-r code.ct-secret{cursor:pointer;letter-spacing:1px}
    .ct-acc-r a{color:var(--primary);word-break:break-all;font-size:11.5px}
    .ct-cp{border:1px solid var(--border);background:var(--card);color:var(--muted);border-radius:6px;
        width:26px;height:26px;cursor:pointer;flex:0 0 auto;font-size:12px}
    .ct-cp:hover{color:var(--primary);border-color:var(--primary)}
    .ct-cp.ok{color:#16a34a;border-color:#16a34a}
    .ct-panel-link{display:inline-flex;align-items:center;gap:4px;margin-left:6px;color:var(--primary);
        font-weight:700;text-decoration:none;white-space:nowrap}
    .ct-panel-link:hover{text-decoration:underline}
    .ct-acc-n{margin:10px 0 0;color:var(--muted);font-size:11px;line-height:1.6}
    @media (max-width:820px){
        .ct-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
        .ct-acc-r{grid-template-columns:1fr auto}
        .ct-acc-r > span{grid-column:1 / -1;margin-bottom:-2px}
    }
    .ct-dom{display:flex;align-items:center;gap:6px;margin-top:6px;font-size:11.5px}
    .ct-dom a{color:var(--primary);text-decoration:none;font-weight:600}
    .ct-dom a:hover{text-decoration:underline}
    .ct-domsel{font-size:11px;padding:3px 6px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text);max-width:150px}
    .ct-domgo{font-size:11px;font-weight:700;padding:3px 9px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text);cursor:pointer}
    .ct-domgo:hover{border-color:var(--primary);color:var(--primary)}
    .ct-domx{background:none;border:0;color:var(--muted);cursor:pointer;font-size:14px;line-height:1;padding:0 2px}
    .ct-domx:hover{color:#dc2626}
    .ct-port{font-size:11px;font-family:ui-monospace,Menlo,monospace;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:1px 7px;border-radius:6px;margin-right:4px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="ct-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="ct-head">
    <div class="ct-head-ic"><i class="ri-apps-2-line"></i></div>
    <div><h1>{{ __('client.hosting.containers.title') }}</h1><div class="sub">{{ __('client.hosting.containers.subtitle') }}</div></div>
    <span class="ct-cnt">{{ $policy['max'] < 0 ? $policy['used'].' / ∞' : $policy['used'].' / '.$policy['max'] }}</span>
</div>

@if(! $policy['enabled'])
<div class="ct-card"><div class="ct-note"><i class="ri-lock-line"></i>{{ __('client.hosting.containers.plan_disabled') }}</div></div>
@else

{{-- What a customer comes here to do most days is look at the apps they already
     run - open one, restart it, read its connection details. That sat below a
     catalogue of ninety-eight, so it may as well not have been there. CSS order
     puts the running apps first; the markup order is untouched. --}}
<div class="ct-split">

@if($policy['can_create'])
<div class="ct-card ct-col-side">
    <div class="ct-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.containers.install_title') }}</div>
    @if(empty($templates))
        <div class="ct-empty">{{ __('client.hosting.containers.no_apps') }}</div>
    @else
    <form method="POST" action="{{ route('client.services.containers.store', $service) }}" id="ct-installform" onsubmit="return ctInstalling()">
        @csrf
        <input type="hidden" name="slug" id="ct-slug" value="">

        {{-- Search first: with 98 apps across nine sections, scrolling to find
             one is the slow path. Filtering happens in the browser because the
             whole catalogue is already on the page. --}}
        <div class="ct-search">
            <i class="ri-search-line"></i>
            <input type="text" id="ct-q" autocomplete="off" class="ct-inp"
                   placeholder="{{ __('client.hosting.containers.search_ph') }}"
                   oninput="ctFilter()" onkeydown="if(event.key==='Enter')event.preventDefault()">
            <button type="button" id="ct-clear" class="ct-clear" onclick="ctClear()" hidden aria-label="{{ __('client.hosting.containers.search_clear') }}"><i class="ri-close-line"></i></button>
            <span class="ct-count" id="ct-count">{{ __('client.hosting.containers.showing', ['count' => count($templates)]) }}</span>
        </div>
        <div class="ct-noresult" id="ct-noresult" hidden>{{ __('client.hosting.containers.search_none') }}</div>

        @foreach($groups as $g)
        <div class="ct-group" data-group="{{ $g['key'] }}">
            <div class="ct-gh">
                <span class="ct-gt">{{ __('client.hosting.containers.group_'.$g['key']) }}</span>
                <span class="ct-gd">{{ __('client.hosting.containers.group_'.$g['key'].'_hint') }}</span>
            </div>
            <div class="ct-apps">
                @foreach($g['apps'] as $t)
                @php
                    // One mark per app, drawn here rather than fetched.
                    //
                    // The catalogue's logo_url points at other people's servers:
                    // most apps have none, and of the ones that do, roughly half
                    // are dead links that render as a broken image. That made the
                    // grid look half-finished and sent every customer's browser to
                    // github/jsdelivr on page load. A letter tile keyed off the
                    // slug is the same for every app, always loads, and leaks
                    // nothing.
                    $ctHue = crc32($t['slug']) % 360;
                    $ctInitial = mb_strtoupper(mb_substr(trim($t['name']) ?: $t['slug'], 0, 1));
                    // An app that asks for more than the plan allows would install
                    // and then be starved, so say so before it is chosen.
                    $ctTooBig = $resources['memory_mb'] > 0 && $t['min_memory_mb'] > $resources['memory_mb'];
                @endphp
                <div class="ct-app{{ $ctTooBig ? ' big' : '' }}" data-slug="{{ $t['slug'] }}"
                     data-find="{{ mb_strtolower($t['name'].' '.$t['slug'].' '.$t['description'].' '.implode(' ', $t['categories'])) }}"
                     onclick="ctPick(this)" title="{{ $t['description'] }}">
                    @if($t['is_popular'])<span class="ct-pop" title="{{ __('client.hosting.containers.popular') }}"><i class="ri-star-fill"></i></span>@endif
                    @if(! empty($logos[$t['slug']]))
                        <img src="{{ $logos[$t['slug']] }}" alt="" loading="lazy" class="ct-logo">
                    @else
                        <div class="ct-mark" style="background:hsl({{ $ctHue }},62%,94%);color:hsl({{ $ctHue }},52%,34%);border-color:hsl({{ $ctHue }},45%,84%)">{{ $ctInitial }}</div>
                    @endif
                    <div class="nm">{{ $t['name'] }}</div>
                    <div class="ds">{{ $t['description'] }}</div>
                    <div class="ct-req">
                        @if($t['min_memory_mb'] > 0)
                        <span class="ct-req-i{{ $ctTooBig ? ' over' : '' }}" title="{{ __('client.hosting.containers.needs_ram') }}"><i class="ri-ram-2-line"></i>{{ $t['min_memory_mb'] >= 1024 ? round($t['min_memory_mb']/1024, 1).' GB' : $t['min_memory_mb'].' MB' }}</span>
                        @endif
                        @if($t['min_cpu_percent'] > 0)
                        <span class="ct-req-i" title="{{ __('client.hosting.containers.needs_cpu') }}"><i class="ri-cpu-line"></i>{{ $t['min_cpu_percent'] }}%</span>
                        @endif
                        @if($t['min_memory_mb'] <= 0 && $t['min_cpu_percent'] <= 0)
                        <span class="ct-req-i light">{{ __('client.hosting.containers.needs_light') }}</span>
                        @endif
                        @if(($t['extra_services'] ?? 0) > 0)
                        {{-- The floor above is the main container's. A template
                             that also starts a database, a cache or an ML worker
                             spends the same allowance on those, so saying "2 GB"
                             on its own would not be true. --}}
                        <span class="ct-req-i" title="{{ __('client.hosting.containers.services_hint') }}"><i class="ri-stack-line"></i>{{ trans_choice('client.hosting.containers.services', $t['extra_services'] + 1, ['count' => $t['extra_services'] + 1]) }}</span>
                        @endif
                    </div>
                    @if($ctTooBig)<div class="ct-over">{{ __('client.hosting.containers.over_plan') }}</div>@endif
                    <button type="button" class="ct-pick" onclick="ctPick(this.closest('.ct-app'));event.stopPropagation()"><i class="ri-download-2-line"></i>{{ __('client.hosting.containers.install') }}</button>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Moved next to whichever app is chosen, rather than living at the
             bottom of a page with ninety-eight cards on it. Kept as one form so
             there is a single name field and a single submit, not ninety-eight. --}}
        <div class="ct-form" id="ct-form" hidden>
            <div class="ct-chosen"><span class="ct-chosen-n" id="ct-chosen"></span></div>
            <div class="ct-fld" style="max-width:240px">
                <label class="ct-lbl">{{ __('client.hosting.containers.name') }}</label>
                <input type="text" name="name" id="ct-name" maxlength="40" pattern="[a-zA-Z0-9-]*" class="ct-inp" placeholder="{{ __('client.hosting.containers.name_ph') }}">
            </div>
            <button type="submit" class="ct-btn" id="ct-submit" disabled><i class="ri-download-2-line"></i><span id="ct-submit-t">{{ __('client.hosting.containers.install') }}</span></button>
            <button type="button" class="ct-cancel" onclick="ctCancel()">{{ __('client.hosting.containers.cancel') }}</button>
            <div class="ct-installnote" id="ct-installnote" hidden>{{ __('client.hosting.containers.installing_note') }}</div>
        </div>
        @if($resources['memory_mb'] > 0 || $resources['cpu_percent'] > 0)
        <div class="ct-note info"><i class="ri-scales-3-line"></i>{{ __('client.hosting.containers.plan_ceiling', [
            'ram' => $resources['memory_mb'] > 0 ? ($resources['memory_mb'] >= 1024 ? round($resources['memory_mb']/1024, 1).' GB' : $resources['memory_mb'].' MB') : __('client.hosting.containers.unlimited'),
            'cpu' => $resources['cpu_percent'] > 0 ? $resources['cpu_percent'].'%' : __('client.hosting.containers.unlimited'),
        ]) }}</div>
        @endif
    </form>
    <div class="ct-note info"><i class="ri-information-line"></i>{{ __('client.hosting.containers.install_hint') }}</div>
    @endif
</div>
@else
<div class="ct-card ct-col-side"><div class="ct-note"><i class="ri-error-warning-line"></i>{{ __('client.hosting.containers.limit_reached') }} ({{ $policy['used'] }}/{{ $policy['max'] }})</div></div>
@endif

<div class="ct-card ct-col-main">
    <div class="ct-ch"><i class="ri-apps-2-line"></i>{{ __('client.hosting.containers.running_title') }}</div>
    @if(empty($containers))
    <div class="ct-empty">{{ __('client.hosting.containers.empty') }}</div>
    @else
    <div class="ct-list">
        @foreach($containers as $c)
        @php
            // Installing an app is only half the job: it has to be reachable on
            // the customer's own address. $domains is an id => name map.
            $linkedId = null;
            foreach ($links as $dId => $l) {
                if (($l['container_id'] ?? '') === $c['id'] && isset($domains[$dId])) { $linkedId = $dId; break; }
            }
            $freeDomains = array_diff_key($domains, $links);
            $acc = $access[$c['id']] ?? null;
            $logo = \App\Models\DockerApp::forContainer($logos, $c['image'], $c['template']);
            $running = $c['state'] === 'running';
        @endphp
        <article class="ct-item">
            <header class="ct-it-head">
                @if($logo)
                <img class="ct-it-logo" src="{{ $logo }}" alt="">
                @else
                <span class="ct-it-mark">{{ strtoupper(substr($c['template'] ?: $c['name'], 0, 1)) }}</span>
                @endif
                <div class="ct-it-id">
                    <div class="ct-name">{{ $c['name'] }}</div>
                    <div class="ct-img">{{ $c['image'] }}</div>
                </div>
                <div class="ct-it-state">
                    @if($running)<span class="ct-run">{{ __('client.hosting.containers.running') }}</span>
                    @elseif($c['state'] === 'restarting')
                        {{-- Restarting on a loop means the app is failing to start.
                             Saying "restarting" leaves the customer watching a
                             spinner that never ends. --}}
                        <span class="ct-warn">{{ __('client.hosting.containers.crashing') }}</span>
                    @else<span class="ct-stop">{{ $c['state'] ?: __('client.hosting.containers.stopped') }}</span>@endif
                </div>
                <div class="ct-acts">
                    @if($running)
                    <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                        <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="restart">
                        <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.restart') }}"><i class="ri-restart-line"></i></button>
                    </form>
                    <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                        <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="stop">
                        <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.stop') }}"><i class="ri-stop-line"></i></button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                        <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="start">
                        <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.start') }}"><i class="ri-play-line"></i></button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('client.services.containers.destroy', $service) }}" onsubmit="return confirm('{{ __('client.hosting.containers.delete_confirm') }}')">@csrf
                        <input type="hidden" name="container_id" value="{{ $c['id'] }}">
                        <button type="submit" class="ct-act danger" title="{{ __('client.hosting.containers.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </div>
            </header>

            @if($c['state'] === 'restarting')
            <div class="ct-hint ct-it-hint">{{ __('client.hosting.containers.crashing_hint') }}</div>
            @endif

            <div class="ct-facts">
                <div class="ct-fact"><span>CPU</span><b>{{ $running ? number_format($c['cpu_percent'], 1).'%' : '—' }}</b></div>
                <div class="ct-fact"><span>RAM</span><b>{{ $running && $c['mem_usage'] >= 1048576 ? number_format($c['mem_usage']/1048576, 0).' MB' : '—' }}@if($running && $c['mem_limit'] > 0) / {{ number_format($c['mem_limit']/1048576, 0) }} MB @endif</b></div>
                <div class="ct-fact"><span>{{ __('client.hosting.containers.ports') }}</span>
                    <b>@forelse($c['ports'] as $p)<span class="ct-port">{{ $p }}</span>@empty <span>—</span> @endforelse</b>
                </div>
                <div class="ct-fact ct-fact-dom"><span>{{ __('client.hosting.containers.domain') }}</span>
                    <b>
                    @if($linkedId)
                        <a href="https://{{ $domains[$linkedId] }}" target="_blank" rel="noopener">{{ $domains[$linkedId] }}</a>
                        <form method="POST" action="{{ route('client.services.containers.unlink', $service) }}" style="display:inline">@csrf
                            <input type="hidden" name="domain_id" value="{{ $linkedId }}">
                            <button type="submit" class="ct-domx" title="{{ __('client.hosting.containers.domain_unlink') }}">&times;</button>
                        </form>
                    @elseif($running && $freeDomains)
                        <form method="POST" action="{{ route('client.services.containers.link', $service) }}" class="ct-dom">@csrf
                            <input type="hidden" name="container_id" value="{{ $c['id'] }}">
                            <select name="domain_id" class="ct-domsel">
                                @foreach($freeDomains as $dId => $dName)
                                <option value="{{ $dId }}">{{ $dName }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="ct-domgo">{{ __('client.hosting.containers.domain_link') }}</button>
                        </form>
                    @elseif(! $running)
                        <span class="ct-hint">{{ __('client.hosting.containers.domain_needs_running') }}</span>
                    @else
                        <span class="ct-hint">{{ __('client.hosting.containers.domain_none') }}</span>
                    @endif
                    </b>
                </div>
            </div>

            @if($acc && $acc->hasAnything())
            <div class="ct-it-foot">
                @if($acc->accessUrl())
                {{-- The address is the first thing anyone wants after installing,
                     so it is a button, not a line inside a disclosure. --}}
                <a class="ct-go" href="{{ $acc->accessUrl() }}" target="_blank" rel="noopener">
                    <i class="ri-external-link-line"></i>{{ __('client.hosting.containers.open_app') }}
                </a>
                @endif
                @if($acc->items() || $acc->notes())
                <details class="ct-acc">
                    <summary>{{ __('client.hosting.containers.access_title') }}</summary>
                    <div class="ct-acc-b">
                        @foreach($acc->items() as $label => $value)
                        @php
                            // A generated password should not sit on screen in a
                            // shared office; it is one click away instead.
                            $secret = (bool) preg_match('/(password|passwd|secret|token|key)/i', $label);
                            $isUrl = Str::startsWith($value, ['http://', 'https://']);
                        @endphp
                        <div class="ct-acc-r">
                            <span>{{ $label }}</span>
                            @if($isUrl)
                                <a href="{{ $value }}" target="_blank" rel="noopener">{{ $value }}</a>
                            @else
                                <code @class(['ct-secret' => $secret]) data-value="{{ $value }}">{{ $secret ? str_repeat('•', 10) : $value }}</code>
                            @endif
                            <button type="button" class="ct-cp" data-value="{{ $value }}" title="{{ __('client.hosting.containers.copy') }}"><i class="ri-file-copy-line"></i></button>
                        </div>
                        @endforeach
                        @if($acc->notes())<p class="ct-acc-n">{{ $acc->notes() }}</p>@endif
                    </div>
                </details>
                @endif
            </div>
            @endif
        </article>
        @endforeach
    </div>
    {{-- A shell inside a container is a real need - installing a plugin by hand,
         reading a log, running a migration. The panel already has a terminal per
         container and the hosting account may use it, so this points at it
         instead of describing it and leaving the customer to find the door. --}}
    <div class="ct-note info">
        <i class="ri-terminal-box-line"></i>
        <span>{{ __('client.hosting.containers.panel_hint') }}
            <a class="ct-panel-link" href="{{ route('client.services.login', ['service' => $service, 'to' => 'docker']) }}">
                {{ __('client.hosting.containers.open_panel') }} <i class="ri-arrow-right-line"></i>
            </a>
        </span>
    </div>
    @endif
</div>
</div>

@endif

<script>
// A generated password is masked until it is asked for, and every value can be
// copied - reading a twenty-four character password off the screen and typing it
// into a login form is nobody's idea of a good time.
(function(){
    document.querySelectorAll('code.ct-secret').forEach(function(el){
        el.addEventListener('click', function(){
            var v = el.getAttribute('data-value') || '';
            if (el.dataset.shown === '1') { el.textContent = '\u2022'.repeat(10); el.dataset.shown = '0'; }
            else { el.textContent = v; el.dataset.shown = '1'; }
        });
    });
    document.querySelectorAll('.ct-cp').forEach(function(btn){
        btn.addEventListener('click', function(){
            var v = btn.getAttribute('data-value') || '';
            var done = function(){
                btn.classList.add('ok');
                setTimeout(function(){ btn.classList.remove('ok'); }, 1400);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(v).then(done);
                return;
            }
            // Plain http, which a freshly installed app is served over until a
            // domain is pointed at it, has no clipboard API.
            var t = document.createElement('textarea');
            t.value = v; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(t);
        });
    });
})();

// The catalogue folds. It carries ninety-eight apps, so it opens to a peek -
// enough to show it is a grid of apps and that there is more - and the whole
// header is the toggle. Built here rather than in the markup so the same
// blocks keep serving the plain, unfolded page when scripting is off.
(function(){
    var side = document.querySelector('.ct-col-side');
    if (!side || !side.querySelector('.ct-apps')) { return; }
    var head = side.querySelector('.ct-ch');
    if (head) {
        var chev = document.createElement('i');
        chev.className = 'ri-arrow-down-s-line ct-chev';
        head.appendChild(chev);
        head.addEventListener('click', function(e){
            if (e.target.closest('input,button,a,select')) { return; }
            ctToggleCatalogue();
        });
    }
    var fade = document.createElement('div');
    fade.className = 'ct-fade';
    side.appendChild(fade);

    var bar = document.createElement('div');
    bar.className = 'ct-more';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = '<i class="ri-add-line"></i><span>' + @json(__('client.hosting.containers.browse_all')) + '</span>';
    btn.addEventListener('click', function(){ ctToggleCatalogue(); });
    bar.appendChild(btn);
    side.appendChild(bar);

    window.ctToggleCatalogue = function(open){
        var want = (open === undefined) ? !side.classList.contains('is-open') : open;
        side.classList.toggle('is-open', want);
        btn.querySelector('span').textContent = want
            ? @json(__('client.hosting.containers.collapse'))
            : @json(__('client.hosting.containers.browse_all'));
        btn.querySelector('i').className = want ? 'ri-arrow-up-s-line' : 'ri-add-line';
        if (!want) { side.scrollIntoView({block: 'nearest'}); }
    };

    // Searching or picking an app is a request to see the whole catalogue.
    var search = side.querySelector('.ct-search input');
    if (search) { search.addEventListener('input', function(){ window.ctToggleCatalogue(true); }); }
    side.addEventListener('click', function(e){
        if (e.target.closest('.ct-app')) { window.ctToggleCatalogue(true); }
    });
})();

// Pick an app before the install button becomes usable — a deploy without a
// chosen template is the one mistake this form can make.
// The install box follows the chosen app instead of sitting at the bottom of
// the page. It stays a single form - one name field, one submit - and is moved
// in the DOM to just after the selected card, spanning the grid row so the
// layout does not jump.
function ctPick(el){
    document.querySelectorAll('.ct-app').forEach(function(a){ a.classList.remove('on'); });
    el.classList.add('on');

    var form = document.getElementById('ct-form');
    document.getElementById('ct-slug').value = el.getAttribute('data-slug') || '';
    document.getElementById('ct-chosen').textContent = (el.querySelector('.nm') || {}).textContent || '';
    document.getElementById('ct-submit').disabled = false;

    el.insertAdjacentElement('afterend', form);
    form.hidden = false;
    document.getElementById('ct-name').focus({preventScroll: true});
}

// Installing pulls container images and can take minutes. Without this the
// button simply sat there: the page looked frozen and people clicked again.
function ctInstalling(){
    var b = document.getElementById('ct-submit');
    if (b.dataset.busy === '1') { return false; }
    b.dataset.busy = '1';
    b.disabled = true;
    b.querySelector('i').className = 'ri-loader-4-line ct-spin';
    document.getElementById('ct-submit-t').textContent = @json(__('client.hosting.containers.installing'));
    var note = document.getElementById('ct-installnote');
    if (note) { note.hidden = false; }

    return true;
}

function ctCancel(){
    var form = document.getElementById('ct-form');
    form.hidden = true;
    document.getElementById('ct-slug').value = '';
    document.getElementById('ct-name').value = '';
    document.getElementById('ct-submit').disabled = true;
    document.querySelectorAll('.ct-app').forEach(function(a){ a.classList.remove('on'); });
}

// Filtering runs here rather than on the server: the catalogue is already on
// the page, so typing should not cost a round trip. Every word must match, so
// "wordpress php" narrows instead of widening. Sections with nothing left hide
// themselves, and the counter says how much is showing.
function ctFilter(){
    var q = (document.getElementById('ct-q').value || '').toLowerCase().trim();
    var terms = q ? q.split(/\s+/) : [];
    document.getElementById('ct-clear').hidden = !q;

    var shown = 0;
    document.querySelectorAll('.ct-group').forEach(function(g){
        var vis = 0;
        g.querySelectorAll('.ct-app').forEach(function(a){
            var hay = a.getAttribute('data-find') || '';
            var ok = terms.every(function(t){ return hay.indexOf(t) !== -1; });
            a.hidden = !ok;
            if (ok) { vis++; shown++; }
        });
        g.hidden = vis === 0;
    });

    // A filter that hides the chosen card would leave the install box floating
    // next to nothing, so the choice goes with it.
    var on = document.querySelector('.ct-app.on');
    if (on && on.hidden) { ctCancel(); }

    document.getElementById('ct-noresult').hidden = shown !== 0;
    var c = document.getElementById('ct-count');
    c.textContent = c.getAttribute('data-tpl').replace(':count', shown);
}

function ctClear(){
    var i = document.getElementById('ct-q');
    i.value = '';
    ctFilter();
    i.focus();
}

document.getElementById('ct-count').setAttribute('data-tpl', @json(__('client.hosting.containers.showing')));
</script>

@endsection
