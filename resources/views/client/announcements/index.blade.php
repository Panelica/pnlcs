@extends('client.layouts.app')
@section('title', 'Announcements')
@section('content')
<h1 class="text-2xl font-bold mb-6">Announcements</h1>

@if($announcements->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
        <p class="text-slate-400">No announcements at this time.</p>
    </div>
@else
    <div class="space-y-4">
        @foreach($announcements as $announcement)
        <a href="{{ route('client.announcements.show', $announcement) }}" class="block bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-2">{{ $announcement->title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">{{ strip_tags($announcement->announcement) }}</p>
                </div>
                <time class="text-xs text-slate-400 whitespace-nowrap flex-shrink-0">{{ $announcement->created_at->format('d M Y') }}</time>
            </div>
        </a>
        @endforeach
    </div>
    <div class="mt-6">{{ $announcements->links() }}</div>
@endif
@endsection
