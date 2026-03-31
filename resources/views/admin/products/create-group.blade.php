@extends("admin.layouts.app")
@section("title", "Create Product Group")
@section("content")
<div class="max-w-xl">
    <h1 class="text-2xl font-bold mb-6">Create Product Group</h1>
    <form method="POST" action="{{ route("admin.products.groups.store") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
        @csrf
        <div><label class="block text-sm font-medium mb-1">Group Name *</label><input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium mb-1">Headline</label><input type="text" name="headline" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"></div>
        <div><label class="block text-sm font-medium mb-1">Tagline</label><input type="text" name="tagline" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"></div>
        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Create Group</button>
            <a href="{{ route("admin.products.index") }}" class="px-6 py-2 bg-slate-100 hover:bg-slate-200 font-medium rounded-lg">Cancel</a>
        </div>
    </form>
</div>
@endsection
