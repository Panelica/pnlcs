@extends("client.layouts.app")
@section("title", __("client.kb.title"))
@section("content")

<div class="pn-page-header" style="margin-bottom:24px">
    <div>
        <h1 class="pn-page-title">{{ __('client.kb.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.kb.page_subtitle') }}</p>
    </div>
</div>

<div class="pn-card" style="padding:24px 28px;margin-bottom:32px">
    <form method="GET" action="{{ route('client.kb.index') }}" style="display:flex;gap:10px;max-width:560px;margin:0 auto">
        <div style="position:relative;flex:1">
            <svg width="16" height="16" fill="none" stroke="var(--muted)" viewBox="0 0 24 24" style="position:absolute;left:14px;top:50%;transform:translateY(-50%)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $searchQuery ?? '' }}" class="form-control" style="padding-left:40px;height:42px" placeholder="{{ __('client.kb.search_placeholder') }}">
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;padding:0 20px">{{ __('common.actions.search') }}</button>
    </form>
    @if(isset($searchQuery) && $searchQuery)
    <div class="text-muted text-sm" style="margin-top:12px;text-align:center">
        {{ __('client.kb.results_for') }} <strong style="color:var(--text)">{{ $searchQuery }}</strong>
    </div>
    @endif
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:24px">
@forelse($categories as $cat)
    <div class="pn-card" style="padding:0;overflow:hidden">
        <div style="padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-light, #e8f0fb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="18" height="18" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
                <h2 style="font-size:15px;font-weight:700;color:var(--text);margin:0">{{ $cat->name }}</h2>
                @if($cat->description)<p style="font-size:12px;color:var(--muted);margin:2px 0 0">{{ $cat->description }}</p>@endif
            </div>
            <span style="margin-left:auto;font-size:11px;color:var(--muted);background:var(--primary-light, #e8f0fb);padding:2px 8px;border-radius:10px;font-weight:600">{{ $cat->articles->count() }}</span>
        </div>
        @if($cat->articles->isNotEmpty())
        <div style="padding:8px 0">
            @foreach($cat->articles->sortBy('sort_order') as $article)
            <a href="{{ route('client.kb.show', $article) }}" style="display:flex;align-items:center;gap:10px;padding:9px 22px;text-decoration:none;color:inherit;transition:background 0.12s" onmouseover="this.style.background='var(--primary-light, #f0f5ff)'" onmouseout="this.style.background='transparent'">
                <svg width="13" height="13" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="flex-shrink:0;opacity:0.6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span style="font-size:13.5px;color:var(--primary);font-weight:500">{{ $article->title }}</span>
            </a>
            @endforeach
        </div>
        @else
        <div style="padding:20px 22px;text-align:center">
            <p class="text-muted text-sm">{{ __('client.kb.no_articles') }}</p>
        </div>
        @endif
    </div>
@empty
    <div class="pn-card" style="grid-column:1/-1;padding:48px 24px;text-align:center">
        <p class="text-muted">{{ __('client.kb.no_articles_available') }}</p>
    </div>
@endforelse
</div>

@endsection
