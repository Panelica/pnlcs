@extends("client.layouts.app")
@section("title", "Knowledge Base")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Knowledge Base</h1>
        <p class="pn-page-subtitle">Find answers to frequently asked questions.</p>
    </div>
</div>

<div class="pn-card mb-24" style="padding:20px 24px">
    <form method="GET" action="{{ route("client.kb.index") }}" style="display:flex;gap:10px;max-width:520px">
        <div style="position:relative;flex:1">
            <svg width="16" height="16" fill="none" stroke="var(--muted)" viewBox="0 0 24 24" style="position:absolute;left:12px;top:50%;transform:translateY(-50%)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $searchQuery ?? "" }}" class="form-control" style="padding-left:38px" placeholder="Search knowledge base...">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
    @if(isset($searchQuery) && $searchQuery)
    <div class="text-muted text-sm" style="margin-top:10px">
        Showing results for: <strong style="color:var(--text)">{{ $searchQuery }}</strong>
    </div>
    @endif
</div>

@forelse($categories as $cat)
<div class="mb-24">
    @if(isset($cat->name))
    <h2 style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
        <svg width="16" height="16" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        {{ $cat->name }}
    </h2>
    @endif
    @if(isset($cat->articles) && $cat->articles->isNotEmpty())
    <div style="display:flex;flex-direction:column;gap:6px">
        @foreach($cat->articles as $article)
        <a href="{{ route("client.kb.show", $article) }}" style="text-decoration:none;color:inherit">
            <div class="pn-card" style="padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:16px;transition:all 0.15s">
                <div style="display:flex;align-items:center;gap:12px;min-width:0">
                    <svg width="14" height="14" fill="none" stroke="var(--muted)" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <div>
                        <div style="font-size:13.5px;font-weight:600;color:var(--primary)">{{ $article->title }}</div>
                        @if($article->description)<div class="text-muted text-sm" style="margin-top:2px">{{ Str::limit($article->description, 90) }}</div>@endif
                    </div>
                </div>
                <span class="text-muted text-sm" style="white-space:nowrap;flex-shrink:0">{{ $article->views ?? 0 }} views</span>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <p class="text-muted text-sm">No articles in this category.</p>
    @endif
</div>
@empty
<div class="pn-card">
    <div class="pn-empty">
        <div class="pn-empty-icon">&#128218;</div>
        <p>No knowledge base articles available yet.</p>
    </div>
</div>
@endforelse

@endsection
