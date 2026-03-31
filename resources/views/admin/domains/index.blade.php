@extends("admin.layouts.app")
@section("title", "Domains")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Domains</h1>
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
    <x-heroicon-o-inbox class="w-12 h-12 text-slate-300 mx-auto mb-4" />
    <p class="text-slate-500">No domains yet. They will appear here once created.</p>
</div>
@endsection
