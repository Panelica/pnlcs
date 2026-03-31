@extends('client.layouts.app')
@section('title', $announcement->title)
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('client.announcements.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; All Announcements</a>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-8">
        <header class="mb-6 pb-6 border-b border-slate-200 dark:border-slate-700">
            <h1 class="text-2xl font-bold mb-2">{{ $announcement->title }}</h1>
            <time class="text-sm text-slate-400">{{ $announcement->created_at->format('d F Y, H:i') }}</time>
        </header>
        <div class="prose prose-slate dark:prose-invert max-w-none text-sm leading-relaxed">
            {!! nl2br(e($announcement->announcement)) !!}
        </div>
    </div>
</div>
@endsection
