@extends('admin.layouts.app')
@section('title', 'PHP Info')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">PHP Information</h1>
    <div class="flex items-center gap-2">
        <span class="text-sm font-mono text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1.5 rounded-lg">PHP {{ PHP_VERSION }}</span>
    </div>
</div>

<x-flash-message/>

@php
$iniPath = php_ini_loaded_file();
$memoryLimit = ini_get('memory_limit');
$maxExecTime = ini_get('max_execution_time');
$uploadMaxSize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxInputVars = ini_get('max_input_vars');
$displayErrors = ini_get('display_errors') ? 'On' : 'Off';
$errorReporting = error_reporting();
$timezone = ini_get('date.timezone') ?: date_default_timezone_get();
$extensions = get_loaded_extensions();
sort($extensions);

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'curl', 'fileinfo', 'bcmath', 'ctype', 'xml', 'zip'];
@endphp

{{-- Key Settings Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
    @php
    $settings = [
        ['label' => 'Memory Limit', 'value' => $memoryLimit, 'icon' => 'circle-stack'],
        ['label' => 'Max Execution', 'value' => $maxExecTime . 's', 'icon' => 'clock'],
        ['label' => 'Upload Max Size', 'value' => $uploadMaxSize, 'icon' => 'arrow-up-tray'],
        ['label' => 'POST Max Size', 'value' => $postMaxSize, 'icon' => 'document-text'],
        ['label' => 'Max Input Vars', 'value' => $maxInputVars, 'icon' => 'variable'],
        ['label' => 'Display Errors', 'value' => $displayErrors, 'icon' => 'exclamation-triangle'],
        ['label' => 'Timezone', 'value' => $timezone, 'icon' => 'globe-alt'],
        ['label' => 'Extensions', 'value' => count($extensions), 'icon' => 'puzzle-piece'],
    ];
    @endphp
    @foreach($settings as $setting)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase tracking-wider mb-1">{{ $setting['label'] }}</p>
        <p class="text-lg font-bold text-slate-900 dark:text-white truncate">{{ $setting['value'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- php.ini Info --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-emerald-500 rounded-lg flex items-center justify-center">
                <x-heroicon-o-document-text class="w-5 h-5 text-white"/>
            </div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Configuration Files</h2>
        </div>
        <dl class="space-y-2">
            <div>
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-0.5">Loaded php.ini</dt>
                <dd class="text-sm font-mono text-slate-900 dark:text-white break-all">{{ $iniPath ?: 'No INI file loaded' }}</dd>
            </div>
            @php
            $additionalIni = php_ini_scanned_files();
            @endphp
            @if($additionalIni)
            <div class="mt-3">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Additional ini files scanned</dt>
                @foreach(array_filter(explode(",\n", $additionalIni)) as $file)
                <dd class="text-xs font-mono text-slate-600 dark:text-slate-300 mb-0.5">{{ trim($file) }}</dd>
                @endforeach
            </div>
            @endif
        </dl>
    </div>

    {{-- Required Extensions Check --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-indigo-500 rounded-lg flex items-center justify-center">
                <x-heroicon-o-check-badge class="w-5 h-5 text-white"/>
            </div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Required Extensions</h2>
        </div>
        <div class="grid grid-cols-2 gap-2">
            @foreach($requiredExtensions as $ext)
            @php $loaded = extension_loaded($ext); @endphp
            <div class="flex items-center gap-2 p-2 rounded-lg {{ $loaded ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                @if($loaded)
                    <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0"/>
                @else
                    <x-heroicon-s-x-circle class="w-4 h-4 text-red-500 shrink-0"/>
                @endif
                <span class="text-xs font-mono {{ $loaded ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">{{ $ext }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- All Extensions --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mt-6 p-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 bg-purple-500 rounded-lg flex items-center justify-center">
            <x-heroicon-o-puzzle-piece class="w-5 h-5 text-white"/>
        </div>
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Loaded Extensions ({{ count($extensions) }})</h2>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
        @foreach($extensions as $ext)
        <span class="inline-flex items-center justify-center px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-mono rounded">{{ $ext }}</span>
        @endforeach
    </div>
</div>
@endsection
