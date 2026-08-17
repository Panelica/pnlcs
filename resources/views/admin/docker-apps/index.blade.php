@extends('admin.layouts.app')
@section('title', __('admin.docker_apps.title'))
@section('content')

<style>
    .da-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
    .da-card{border:1px solid #e3e3e3;border-radius:10px;padding:12px;background:#fff}
    .da-top{display:flex;align-items:center;gap:10px;margin-bottom:9px}
    .da-img{width:44px;height:44px;border-radius:10px;border:1px solid #e3e3e3;object-fit:contain;background:#fafafa;flex:0 0 auto}
    .da-mark{width:44px;height:44px;border-radius:10px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;flex:0 0 auto}
    .da-nm{font-size:13px;font-weight:700;line-height:1.2}
    .da-sl{font-size:11px;color:#888;word-break:break-all}
    .da-row{display:flex;gap:5px;margin-top:6px}
    .da-row input[type=text],.da-row input[type=file]{flex:1;min-width:0;font-size:11px;padding:4px 6px;border:1px solid #ddd;border-radius:6px}
    .da-row button{font-size:11px;padding:4px 9px;border-radius:6px;border:1px solid #ddd;background:#f7f7f7;cursor:pointer;white-space:nowrap}
    .da-row button:hover{background:#eee}
    .da-del{color:#b3261e;border-color:#f0c8c5 !important}
    .da-tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .da-sell{margin-top:8px;padding-top:8px;border-top:1px dashed #e8e8e8}
    .da-flags{display:flex;gap:9px;align-items:center;font-size:11px;color:#555;flex-wrap:wrap}
    .da-flags label{display:flex;align-items:center;gap:4px}
    .da-flags input[type=number]{font-size:11px;padding:2px 4px;border:1px solid #ddd;border-radius:5px}
    .da-off{font-size:10px;font-weight:700;color:#b3261e;margin-left:4px}
    .da-src{font-size:11.5px;color:#2a5db0;background:#eef4ff;border:1px solid #d6e4ff;border-radius:8px;
        padding:8px 11px;margin-bottom:12px;line-height:1.55}
    .da-badge{font-size:11px;padding:2px 7px;border-radius:999px;background:#eef4ff;color:#2a5db0;font-weight:700}
</style>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <h1>{{ __('admin.docker_apps.title') }}</h1>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="da-badge">{{ __('admin.docker_apps.have_count', ['have' => $totalWithLogo, 'total' => count($templates)]) }}</span>
        <span class="da-badge" style="background:#eefaf0;color:#1d7a3c;">{{ __('admin.docker_apps.sellable_count', ['count' => $totalSellable]) }}</span>
    </div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <p style="font-size:12px;color:#666;margin:0 0 10px;line-height:1.6;">{{ __('admin.docker_apps.intro') }}</p>
        {{-- Where this list comes from, said plainly: the panel decides what
             exists and what is switched on, this page decides what is sold. --}}
        <div class="da-src"><i class="fas fa-info-circle"></i> {{ __('admin.docker_apps.source_note') }}</div>
        <div class="da-tools">
            <form method="GET" action="{{ route('admin.docker-apps.index') }}" class="da-tools" style="margin:0;">
                <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('admin.docker_apps.search_ph') }}" style="font-size:12px;padding:5px 8px;border:1px solid #ddd;border-radius:6px;">
                <label style="font-size:12px;display:flex;align-items:center;gap:5px;">
                    <input type="checkbox" name="missing" value="1" {{ $missingOnly ? 'checked' : '' }} onchange="this.form.submit()">
                    {{ __('admin.docker_apps.only_missing') }}
                </label>
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.docker_apps.filter') }}</button>
            </form>

            {{-- Most apps have no usable image, so doing them one by one is not a
                 realistic starting point; this takes every link the panel still
                 has and reports what could not be fetched. --}}
            <form method="POST" action="{{ route('admin.docker-apps.import') }}" style="margin:0;"
                  onsubmit="return confirm(@js(__('admin.docker_apps.import_confirm')))">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.docker_apps.import_missing') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.docker-apps.import') }}" style="margin:0;"
                  onsubmit="return confirm(@js(__('admin.docker_apps.import_overwrite_confirm')))">
                @csrf
                <input type="hidden" name="overwrite" value="1">
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.docker_apps.import_overwrite') }}</button>
            </form>
        </div>
    </div>
</div>

@if($error)
<div class="card" style="margin-bottom:15px;"><div class="card-body" style="color:#b3261e;font-size:13px;">{{ $error }}</div></div>
@endif

<div class="da-grid">
    @foreach($templates as $t)
    @php
        $hue = crc32($t['slug']) % 360;
        $initial = mb_strtoupper(mb_substr(trim($t['name']) ?: $t['slug'], 0, 1));
        $url = $logos[$t['slug']] ?? null;
    @endphp
    <div class="da-card">
        <div class="da-top">
            @if($url)
                <img src="{{ $url }}" alt="" class="da-img">
            @else
                {{-- What the customer sees today when there is no image. --}}
                <div class="da-mark" style="background:hsl({{ $hue }},62%,94%);color:hsl({{ $hue }},52%,34%);border-color:hsl({{ $hue }},45%,84%)">{{ $initial }}</div>
            @endif
            <div style="min-width:0">
                <div class="da-nm">{{ $t['name'] }}
                    @if($t['is_featured'])<span title="{{ __('admin.docker_apps.featured') }}" style="color:#f0a92b">&#9733;</span>@endif
                    @if(($rows[$t['slug']] ?? null) && ! $rows[$t['slug']]->is_sellable)<span class="da-off">{{ __('admin.docker_apps.not_sold') }}</span>@endif
                </div>
                <div class="da-sl">{{ $t['slug'] }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.docker-apps.upload') }}" enctype="multipart/form-data" class="da-row">
            @csrf
            <input type="hidden" name="slug" value="{{ $t['slug'] }}">
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp,.gif" required>
            <button type="submit">{{ __('admin.docker_apps.upload') }}</button>
        </form>

        <form method="POST" action="{{ route('admin.docker-apps.fetch') }}" class="da-row">
            @csrf
            <input type="hidden" name="slug" value="{{ $t['slug'] }}">
            <input type="text" name="url" placeholder="{{ __('admin.docker_apps.url_ph') }}" required>
            <button type="submit">{{ __('admin.docker_apps.fetch') }}</button>
        </form>

        @if($url)
        <form method="POST" action="{{ route('admin.docker-apps.destroy') }}" class="da-row"
              onsubmit="return confirm(@js(__('admin.docker_apps.remove_confirm')))">
            @csrf
            <input type="hidden" name="slug" value="{{ $t['slug'] }}">
            <button type="submit" class="da-del" style="flex:1">{{ __('admin.docker_apps.remove') }}</button>
        </form>
        @endif

        {{-- Commercial side: whether we offer this app at all, whether it leads
             the grid, and the one line it says on the card. --}}
        @php $row = $rows[$t['slug']] ?? null; @endphp
        <form method="POST" action="{{ route('admin.docker-apps.selling') }}" class="da-sell">
            @csrf
            <input type="hidden" name="slug" value="{{ $t['slug'] }}">
            <div class="da-flags">
                <label><input type="checkbox" name="is_sellable" value="1" {{ ! $row || $row->is_sellable ? 'checked' : '' }}> {{ __('admin.docker_apps.sellable') }}</label>
                <label><input type="checkbox" name="is_featured" value="1" {{ $row?->is_featured ? 'checked' : '' }}> {{ __('admin.docker_apps.featured') }}</label>
                <input type="number" name="sort_order" min="0" max="9999" value="{{ $row?->sort_order ?? 0 }}" title="{{ __('admin.docker_apps.sort_order') }}" style="width:58px">
            </div>
            <div class="da-row">
                <input type="text" name="tagline" maxlength="160" value="{{ $row?->tagline }}" placeholder="{{ __('admin.docker_apps.tagline_ph') }}">
                <button type="submit">{{ __('admin.docker_apps.save') }}</button>
            </div>
        </form>
    </div>
    @endforeach
</div>

@if(empty($templates) && ! $error)
<div class="card"><div class="card-body" style="font-size:13px;color:#777;">{{ __('admin.docker_apps.none_match') }}</div></div>
@endif

@endsection
