@extends('admin.layouts.app')
@section('title', 'Downloads')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Downloads</h1>
    <div class="flex gap-2">
        <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-dl-category'))"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Category
        </button>
        <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-download'))"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Download
        </button>
    </div>
</div>

<x-flash-message/>

@if($categories->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No download categories yet" description="Create a category to organize your downloads." icon="document"/>
    </div>
@else
    <div class="space-y-6">
        @foreach($categories as $category)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-folder class="w-5 h-5 text-amber-500"/>
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ $category->name }}</h2>
                    <span class="text-xs text-slate-400 bg-slate-200 dark:bg-slate-600 px-2 py-0.5 rounded-full">{{ $category->downloads->count() }} files</span>
                </div>
            </div>
            @if($category->downloads->isEmpty())
                <div class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 italic">No downloads in this category yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                                <th class="text-left px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                                <th class="text-left px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Downloads</th>
                                <th class="text-right px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($category->downloads as $download)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 text-slate-400"/>
                                        <a href="{{ $download->location }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">{{ $download->title }}</a>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-slate-500 dark:text-slate-400">{{ Str::limit($download->description, 60) }}</td>
                                <td class="px-6 py-3 text-slate-500 dark:text-slate-400">{{ number_format($download->download_count) }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end">
                                        <x-confirm-delete action="{{ route('admin.config.downloads.destroy', $download) }}"/>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endforeach
    </div>
@endif

{{-- Add Category Modal --}}
<x-modal name="add-dl-category" title="New Download Category">
    <form method="POST" action="{{ route('admin.config.downloads.categories.store') }}">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Category Name</label>
            <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-dl-category'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Create Category</button>
        </div>
    </form>
</x-modal>

{{-- Add Download Modal --}}
<x-modal name="add-download" title="New Download" max-width="xl">
    <form method="POST" action="{{ route('admin.config.downloads.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Category</label>
                <select name="category_id" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select category...</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location (URL or path)</label>
                <input type="text" name="location" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-download'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Add Download</button>
        </div>
    </form>
</x-modal>
@endsection
