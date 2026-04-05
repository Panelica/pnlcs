@extends('client.layouts.app')
@section('title', $article->title)
@section('content')

<style>
.kb-wrap{max-width:820px;margin:0 auto}
.kb-back{display:inline-flex;align-items:center;gap:6px;color:var(--primary);text-decoration:none;font-size:13px;font-weight:500;margin-bottom:16px;transition:opacity 0.15s}
.kb-back:hover{opacity:0.7}
.kb-header{margin-bottom:20px}
.kb-breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-bottom:12px;flex-wrap:wrap}
.kb-breadcrumb a{color:var(--primary);text-decoration:none}
.kb-breadcrumb a:hover{text-decoration:underline}
.kb-title{font-size:24px;font-weight:700;color:var(--text);line-height:1.35;margin:0}
.kb-meta{display:flex;align-items:center;gap:14px;margin-top:10px}
.kb-meta-badge{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:var(--muted);background:var(--primary-light, #e8f0fb);padding:4px 10px;border-radius:6px;font-weight:500}
.kb-meta-date{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px}
.kb-content-card{background:var(--card, #fff);border:1px solid var(--border);border-radius:var(--radius, 12px);padding:32px 36px;margin-bottom:20px;box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,0.08))}
.kb-body{font-size:14.5px;line-height:1.9;color:var(--text)}
.kb-helpful{background:var(--card, #fff);border:1px solid var(--border);border-radius:var(--radius, 12px);padding:24px;text-align:center;box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,0.08));margin-bottom:20px}
.kb-helpful-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:12px}
.kb-helpful-btns{display:flex;justify-content:center;gap:10px}
.kb-helpful-btn{padding:8px 24px;border-radius:8px;border:1px solid var(--border);background:var(--card, #fff);font-size:13px;font-weight:500;cursor:pointer;transition:all 0.15s;color:var(--text)}
.kb-helpful-btn:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light, #f0f5ff)}
.kb-helpful-btn.selected{border-color:var(--primary);color:var(--primary);background:var(--primary-light, #f0f5ff)}
.kb-footer{display:flex;justify-content:space-between;align-items:center}
.kb-footer a{color:var(--primary);text-decoration:none;font-size:13px;font-weight:500;display:flex;align-items:center;gap:5px}
.kb-footer a:hover{text-decoration:underline}
@media(max-width:640px){
    .kb-content-card{padding:20px 18px}
    .kb-title{font-size:20px}
}
</style>

<div class="kb-wrap">
    <a href="{{ route('client.kb.index') }}" class="kb-back">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('client.kb.back_to_kb') }}
    </a>

    <div class="kb-header">
        <div class="kb-breadcrumb">
            <a href="{{ route('client.kb.index') }}">{{ __('client.nav.knowledge_base') }}</a>
            <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            @if($article->category)
            <span>{{ $article->category->name }}</span>
            <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            @endif
            <span style="color:var(--text)">{{ Str::limit($article->title, 60) }}</span>
        </div>
        <h1 class="kb-title">{{ $article->title }}</h1>
        <div class="kb-meta">
            @if($article->category)
            <span class="kb-meta-badge">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                {{ $article->category->name }}
            </span>
            @endif
            <span class="kb-meta-date">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $article->updated_at->format('M d, Y') }}
            </span>
        </div>
    </div>

    <div class="kb-content-card">
        <div class="kb-body">
            {!! nl2br(e($article->article)) !!}
        </div>
    </div>

    <div class="kb-helpful">
        <div class="kb-helpful-title">{{ __('client.kb.was_helpful') }}</div>
        <div class="kb-helpful-btns">
            <button class="kb-helpful-btn" onclick="this.classList.add('selected');this.innerHTML='&#128077; Thanks!';this.disabled=true;this.nextElementSibling.disabled=true">&#128077; Yes</button>
            <button class="kb-helpful-btn" onclick="this.classList.add('selected');this.innerHTML='&#128078; Noted';this.disabled=true;this.previousElementSibling.disabled=true">&#128078; No</button>
        </div>
    </div>

    <div class="kb-footer">
        <a href="{{ route('client.kb.index') }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ __('client.kb.all_articles') }}
        </a>
        @if($article->category)
        <a href="{{ route('client.kb.index') }}">
            {{ __('client.kb.more_in') }} {{ $article->category->name }}
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>
</div>

@endsection
