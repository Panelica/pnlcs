@extends("admin.layouts.app")
@section("title", "Reports")
@section("content")
<h1 class="text-2xl font-bold mb-6">Reports</h1>
@php $categories = collect($reports)->groupBy("category"); @endphp
@foreach($categories as $cat => $items)
<div class="mb-6">
    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ $cat }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($items as $report)
        <a href="{{ route("admin.reports.show", $report["slug"]) }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
            <h4 class="font-medium">{{ $report["name"] }}</h4>
            <p class="text-sm text-slate-500 mt-1">{{ $report["description"] }}</p>
        </a>
        @endforeach
    </div>
</div>
@endforeach
@endsection
