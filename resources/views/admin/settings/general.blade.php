@extends("admin.layouts.app")
@section("title", "Settings")
@section("content")
<h1 class="text-2xl font-bold mb-6">General Settings</h1>
@if(session("success"))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session("success") }}</div>@endif
<form method="POST" action="{{ route("admin.settings.general.update") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4 max-w-2xl">
    @csrf
    <div><label class="block text-sm font-medium mb-1">Company Name</label><input type="text" name="CompanyName" value="{{ $settings["CompanyName"] ?? "" }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
    <div><label class="block text-sm font-medium mb-1">Domain</label><input type="text" name="Domain" value="{{ $settings["Domain"] ?? "" }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
    <div><label class="block text-sm font-medium mb-1">Default Language</label><input type="text" name="DefaultLanguage" value="{{ $settings["DefaultLanguage"] ?? "en" }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition-colors">Save Settings</button>
</form>
@endsection
