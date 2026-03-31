@extends("admin.layouts.app")
@section("title", "To-Do List")
@section("content")
@if(session("success"))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session("success") }}</div>@endif
<h1 class="text-2xl font-bold mb-6">To-Do List</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <p class="text-slate-500 text-sm">This configuration page is ready for data management. Use the API or seeder to populate.</p>
</div>
@endsection
