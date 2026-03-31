@extends('client.layouts.app')
@section('title', 'Knowledge Base')
@section('styles')
<style>
    .kb-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    @media (max-width: 768px) { .kb-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .kb-grid { grid-template-columns: 1fr; } }
    .kb-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px; transition: box-shadow 0.15s; }
    .kb-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
    .kb-icon { width: 36px; height: 36px; background: #e8f0fe; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .kb-icon svg { width: 18px; height: 18px; color: #1a4d80; }
    .kb-title { font-size: 14px; font-weight: 600; color: #1a4d80; margin-bottom: 6px; }
    .kb-desc { font-size: 12px; color: #777; line-height: 1.5; margin-bottom: 10px; }
    .kb-count { font-size: 11px; color: #999; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Knowledge Base</h1>
</div>

@if(isset($searchQuery) && $searchQuery)
<div style="margin-bottom:16px; font-size:13px; color:#555;">
    Search results for: <strong>{{ $searchQuery }}</strong>
</div>
@endif

<div style="margin-bottom:20px;">
    <form method="GET" action="{{ route('client.kb.index') }}" style="display:flex; gap:8px; max-width:400px;">
        <input type="text" name="q" value="{{ $searchQuery ?? '' }}" class="form-control" placeholder="Search knowledge base...">
        <button type="submit" class="btn btn-default">Search</button>
    </form>
</div>

@forelse($categories as $cat)
<div style="margin-bottom:24px;">
    @if(isset($cat->name))
    <h2 style="font-size:14px; font-weight:600; color:#333; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #e0e0e0;">{{ $cat->name }}</h2>
    @endif
    @if(isset($cat->articles) && $cat->articles->isNotEmpty())
    <div style="display:flex; flex-direction:column; gap:6px;">
        @foreach($cat->articles as $article)
        <a href="{{ route('client.kb.show', $article) }}" style="text-decoration:none;">
            <div class="card" style="padding:12px 16px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:13px; font-weight:500; color:#1a4d80;">{{ $article->title }}</div>
                    @if($article->description)<div style="font-size:12px; color:#777; margin-top:2px;">{{ Str::limit($article->description, 100) }}</div>@endif
                </div>
                <span style="font-size:12px; color:#999; white-space:nowrap; margin-left:16px;">{{ $article->views ?? 0 }} views</span>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <p style="font-size:13px; color:#999;">No articles in this category.</p>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center; padding:48px; color:#999;">
        No knowledge base articles available yet.
    </div>
</div>
@endforelse

@endsection
