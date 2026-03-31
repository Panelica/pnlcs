@extends("admin.layouts.app")
@section("title", "Database Status")
@section("content")
<h1 class="text-2xl font-bold mb-6">Database Status</h1>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Database</dt><dd class="font-mono">{{ config("database.connections.mysql.database") }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">MySQL Version</dt><dd class="font-mono">{{ DB::selectOne("SELECT VERSION() as v")->v }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Total Tables</dt><dd class="font-mono">{{ count(DB::select("SHOW TABLES")) }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Connection</dt><dd class="font-mono text-emerald-600">Connected</dd></div>
    </dl>
</div>
@endsection
