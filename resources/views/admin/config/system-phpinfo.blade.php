@extends("admin.layouts.app")
@section("title", "PHP Information")
@section("content")
<h1 class="text-2xl font-bold mb-6">PHP Information</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <dl class="grid grid-cols-2 gap-4 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">PHP Version</dt><dd class="font-mono">{{ phpversion() }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Laravel</dt><dd class="font-mono">{{ app()->version() }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Server</dt><dd class="font-mono">{{ php_uname("s") }} {{ php_uname("r") }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Memory Limit</dt><dd class="font-mono">{{ ini_get("memory_limit") }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Max Upload</dt><dd class="font-mono">{{ ini_get("upload_max_filesize") }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Max POST</dt><dd class="font-mono">{{ ini_get("post_max_size") }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Max Execution</dt><dd class="font-mono">{{ ini_get("max_execution_time") }}s</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">OPcache</dt><dd class="font-mono">{{ function_exists("opcache_get_status") ? "Enabled" : "Disabled" }}</dd></div>
    </dl>
    <h3 class="font-semibold mt-6 mb-3">Loaded Extensions</h3>
    <div class="flex flex-wrap gap-1">
        @foreach(get_loaded_extensions() as $ext)
        <span class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-700 rounded">{{ $ext }}</span>
        @endforeach
    </div>
</div>
@endsection
