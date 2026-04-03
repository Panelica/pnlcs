@extends('client.layouts.app')
@section('title', $article->title)
@section('content')
<div class='container' style='max-width:800px;margin:40px auto;padding:0 20px'>
    <a href='{{ route("client.kb.index") }}' style='color:#1a4d80;text-decoration:none;font-size:14px'>← Back to Knowledge Base</a>
    <h1 style='margin:20px 0 10px;font-size:28px'>{{ $article->title }}</h1>
    <div style='color:#64748b;font-size:13px;margin-bottom:24px'>
        <span>{{ $article->views }} views</span>
        @if($article->category) · <span>{{ $article->category->name }}</span> @endif
        · <span>{{ $article->updated_at->format('M d, Y') }}</span>
    </div>
    <div style='line-height:1.8;color:#334155'>{!! nl2br(e($article->article)) !!}</div>
</div>
@endsection
