@extends('admin.layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Announcements</h1>
    <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-announcement'))"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Announcement
    </button>
</div>

<x-flash-message/>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($announcements->isEmpty())
        <x-empty-state title="No announcements yet" description="Create your first announcement to notify clients." icon="megaphone"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Published</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Created</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($announcements as $announcement)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors" x-data="{ editing: false }">
                        <td class="px-6 py-4">
                            <span class="font-medium text-slate-900 dark:text-white">{{ $announcement->title }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.config.announcements.update', $announcement) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="title" value="{{ $announcement->title }}">
                                <input type="hidden" name="announcement" value="{{ $announcement->announcement }}">
                                <input type="hidden" name="published" value="{{ $announcement->published ? '0' : '1' }}">
                                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition {{ $announcement->published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400 hover:bg-slate-200' }}">
                                    @if($announcement->published)
                                        <x-heroicon-s-check-circle class="w-3.5 h-3.5"/> Published
                                    @else
                                        <x-heroicon-o-eye-slash class="w-3.5 h-3.5"/> Draft
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $announcement->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openEditAnnouncement({{ $announcement->id }}, '{{ addslashes($announcement->title) }}', {{ json_encode($announcement->announcement) }}, {{ $announcement->published ? 'true' : 'false' }})"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <x-heroicon-o-pencil class="w-4 h-4"/>
                                </button>
                                <x-confirm-delete action="{{ route('admin.config.announcements.destroy', $announcement) }}"/>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Add Modal --}}
<x-modal name="add-announcement" title="New Announcement" max-width="2xl">
    <form method="POST" action="{{ route('admin.config.announcements.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Announcement</label>
                <textarea name="announcement" rows="6" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="published" value="1" id="add_published" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                <label for="add_published" class="text-sm text-slate-700 dark:text-slate-300">Publish immediately</label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-announcement'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Create Announcement</button>
        </div>
    </form>
</x-modal>

{{-- Edit Modal --}}
<div x-data="{ open: false, id: null, title: '', announcement: '', published: false }"
     x-on:open-edit-announcement.window="open = true; id = $event.detail.id; title = $event.detail.title; announcement = $event.detail.announcement; published = $event.detail.published"
     x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="open" class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Announcement</h3>
                <button x-on:click="open = false" class="text-slate-400 hover:text-slate-600"><x-heroicon-o-x-mark class="w-5 h-5"/></button>
            </div>
            <form method="POST" x-bind:action="'{{ url('admin/config/announcements') }}/' + id">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" x-model="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Announcement</label>
                        <textarea name="announcement" rows="6" x-model="announcement" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="published" value="1" x-model="published" id="edit_published" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                        <label for="edit_published" class="text-sm text-slate-700 dark:text-slate-300">Published</label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" x-on:click="open = false" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditAnnouncement(id, title, announcement, published) {
    window.dispatchEvent(new CustomEvent('open-edit-announcement', { detail: { id, title, announcement, published } }));
}
</script>
@endsection
