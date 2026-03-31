@extends('client.layouts.app')
@section('title', 'Downloads')
@section('content')
<h1 class="text-2xl font-bold mb-6">Downloads</h1>

@if($categories->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
        <p class="text-slate-400">No downloads available.</p>
    </div>
@else
    <div class="space-y-8">
        @foreach($categories as $category)
        @if($category->downloads->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ $category->name }}</h2>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($category->downloads as $download)
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ $download->title }}</p>
                        @if($download->description)
                            <p class="text-xs text-slate-500 mt-0.5">{{ $download->description }}</p>
                        @endif
                        <p class="text-xs text-slate-400 mt-1">{{ $download->download_count }} downloads</p>
                    </div>
                    @if($download->location)
                    <a href="{{ route('client.downloads.download', $download) }}"
                       class="ml-4 inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download
                    </a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>
@endif
@endsection
