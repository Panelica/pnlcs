@extends('admin.layouts.app')
@section('title', 'Knowledge Base')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Knowledge Base</h1>
    <div class="flex gap-2">
        <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-kb-category'))"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Category
        </button>
        <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-kb-article'))"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Article
        </button>
    </div>
</div>

<x-flash-message/>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    {{-- Categories Sidebar --}}
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Categories</h2>
            </div>
            @if($categories->isEmpty())
                <div class="px-4 py-6 text-center text-sm text-slate-500">No categories yet.</div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($categories as $category)
                    <li class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $category->name }}</p>
                            <p class="text-xs text-slate-400">{{ $category->articles->count() }} articles</p>
                        </div>
                        <span class="text-xs text-slate-400">#{{ $category->sort_order }}</span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Articles Table --}}
    <div class="lg:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Articles</h2>
            </div>
            @php $allArticles = $categories->flatMap(fn($c) => $c->articles); @endphp
            @if($allArticles->isEmpty())
                <x-empty-state title="No articles yet" description="Add your first knowledge base article." icon="document"/>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Category</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Views</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Visibility</th>
                                <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($categories as $category)
                                @foreach($category->articles as $article)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ $article->title }}</td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $category->name }}</td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ number_format($article->views) }}</td>
                                    <td class="px-6 py-4">
                                        @if($article->private)
                                            <x-status-badge status="closed"/>
                                        @else
                                            <x-status-badge status="active"/>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="openEditArticle({{ $article->id }}, {{ $article->category_id }}, '{{ addslashes($article->title) }}', {{ json_encode($article->article) }}, {{ $article->private ? 'true' : 'false' }})"
                                                    class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                                <x-heroicon-o-pencil class="w-4 h-4"/>
                                            </button>
                                            <x-confirm-delete action="{{ route('admin.config.knowledge-base.articles.destroy', $article) }}"/>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Category Modal --}}
<x-modal name="add-kb-category" title="New Category">
    <form method="POST" action="{{ route('admin.config.knowledge-base.categories.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
                <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-kb-category'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Create Category</button>
        </div>
    </form>
</x-modal>

{{-- Add Article Modal --}}
<x-modal name="add-kb-article" title="New Article" max-width="2xl">
    <form method="POST" action="{{ route('admin.config.knowledge-base.articles.store') }}">
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
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Article Content</label>
                <textarea name="article" rows="8" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="private" value="1" id="article_hidden" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                <label for="article_hidden" class="text-sm text-slate-700 dark:text-slate-300">Hidden (private)</label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-kb-article'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Create Article</button>
        </div>
    </form>
</x-modal>

{{-- Edit Article Modal --}}
<div x-data="{ open: false, id: null, category_id: null, title: '', article: '', private: false }"
     x-on:open-edit-article.window="open = true; id = $event.detail.id; category_id = $event.detail.category_id; title = $event.detail.title; article = $event.detail.article; private = $event.detail.private"
     x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="open" class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Article</h3>
                <button x-on:click="open = false" class="text-slate-400 hover:text-slate-600"><x-heroicon-o-x-mark class="w-5 h-5"/></button>
            </div>
            <form method="POST" x-bind:action="'{{ url('admin/config/knowledge-base/articles') }}/' + id">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Category</label>
                        <select name="category_id" x-model="category_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" x-model="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Article Content</label>
                        <textarea name="article" rows="8" x-model="article" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="private" value="1" x-model="private" id="edit_article_hidden" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                        <label for="edit_article_hidden" class="text-sm text-slate-700 dark:text-slate-300">Hidden (private)</label>
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
function openEditArticle(id, category_id, title, article, isPrivate) {
    window.dispatchEvent(new CustomEvent('open-edit-article', { detail: { id, category_id, title, article, private: isPrivate } }));
}
</script>
@endsection
