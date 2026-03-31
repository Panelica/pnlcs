@extends('admin.layouts.app')
@section('title', 'System Database')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Database</h1>
    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-xs font-medium rounded-full">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        Connected
    </span>
</div>

<x-flash-message/>

@php
use Illuminate\Support\Facades\DB;
try {
    $mysqlVersion = DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
} catch (\Exception $e) {
    $mysqlVersion = 'Error: ' . $e->getMessage();
}

try {
    $dbName = DB::getDatabaseName();
} catch (\Exception $e) {
    $dbName = 'Unknown';
}

try {
    $tableCountResult = DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE()");
    $tableCount = $tableCountResult[0]->cnt ?? 0;
} catch (\Exception $e) {
    $tableCount = 0;
}

try {
    $dbSizeResult = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
    $dbSize = ($dbSizeResult[0]->size_mb ?? 0) . ' MB';
} catch (\Exception $e) {
    $dbSize = 'Unknown';
}

try {
    $connectionName = config('database.default');
    $dbHost = config("database.connections.{$connectionName}.host", 'localhost');
    $dbPort = config("database.connections.{$connectionName}.port", '3306');
} catch (\Exception $e) {
    $dbHost = 'Unknown';
    $dbPort = 'Unknown';
}
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    {{-- Connection Info --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                <x-heroicon-o-server class="w-5 h-5 text-white"/>
            </div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Connection</h2>
        </div>
        <dl class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Driver</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white capitalize">{{ config('database.default') }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Server Version</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ $mysqlVersion }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Host</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ $dbHost }}:{{ $dbPort }}</dd>
            </div>
            <div class="flex justify-between items-center py-2">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Database Name</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ $dbName }}</dd>
            </div>
        </dl>
    </div>

    {{-- Statistics --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                <x-heroicon-o-chart-bar class="w-5 h-5 text-white"/>
            </div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Statistics</h2>
        </div>
        <dl class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Tables</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white">{{ number_format($tableCount) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Database Size</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white">{{ $dbSize }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                <dt class="text-sm text-slate-500 dark:text-slate-400">PHP Version</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ PHP_VERSION }}</dd>
            </div>
            <div class="flex justify-between items-center py-2">
                <dt class="text-sm text-slate-500 dark:text-slate-400">Laravel Version</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-white font-mono">{{ app()->version() }}</dd>
            </div>
        </dl>
    </div>
</div>

{{-- Table List --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Database Tables</h2>
    </div>
    @php
    try {
        $tables = DB::select("
            SELECT
                table_name AS name,
                table_rows AS row_count,
                ROUND((data_length + index_length) / 1024, 1) AS size_kb,
                engine AS engine,
                table_collation AS collation,
                create_time
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY table_name ASC
        ");
    } catch (\Exception $e) {
        $tables = [];
    }
    @endphp
    @if(empty($tables))
        <div class="px-6 py-4 text-sm text-slate-500">Unable to retrieve table information.</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="text-left px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Table</th>
                        <th class="text-left px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Engine</th>
                        <th class="text-right px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rows (est.)</th>
                        <th class="text-right px-6 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Size (KB)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($tables as $table)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-2.5"><code class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ $table->name }}</code></td>
                        <td class="px-6 py-2.5 text-slate-500 dark:text-slate-400 text-xs">{{ $table->engine ?? "-" }}</td>
                        <td class="px-6 py-2.5 text-right text-slate-500 dark:text-slate-400">{{ number_format($table->row_count) }}</td>
                        <td class="px-6 py-2.5 text-right text-slate-500 dark:text-slate-400">{{ number_format($table->size_kb, 1) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
