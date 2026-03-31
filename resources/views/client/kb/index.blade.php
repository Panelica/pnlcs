@extends("client.layouts.app")
@section("title", "Knowledge Base")
@section("content")
<h1 class="text-2xl font-bold mb-6">Knowledge Base</h1>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($categories as $cat)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold text-lg mb-2">{{ $cat->name }}</h3>
        @if($cat->description)<p class="text-sm text-slate-500 mb-3">{{ $cat->description }}</p>@endif
        <p class="text-xs text-slate-400">{{ $cat->articles->count() }} articles</p>
    </div>
    @empty
    <div class="col-span-3 text-center py-12 text-slate-400">No knowledge base articles yet.</div>
    @endforelse
</div>
@endsection
