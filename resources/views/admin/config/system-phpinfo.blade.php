@extends('admin.layouts.app')
@section('title', __('admin.phpinfo.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('admin.phpinfo.title') }}</h1>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:15px;">
    <div class="stat-card"><div class="stat-value">{{ PHP_VERSION }}</div><div class="stat-label">{{ __('admin.phpinfo.php_version') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('memory_limit') }}</div><div class="stat-label">{{ __('admin.phpinfo.memory_limit') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('max_execution_time') }}s</div><div class="stat-label">{{ __('admin.phpinfo.max_exec_time') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('upload_max_filesize') }}</div><div class="stat-label">{{ __('admin.phpinfo.upload_limit') }}</div></div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.phpinfo.php_configuration') }}</strong></div>
    <table class="data-table">
        <thead><tr><th>{{ __('admin.phpinfo.directive') }}</th><th>{{ __('admin.phpinfo.local_value') }}</th><th>{{ __('admin.phpinfo.master_value') }}</th></tr></thead>
        <tbody>
        @php
        $directives = ['display_errors','error_reporting','log_errors','max_input_vars','post_max_size','file_uploads','session.gc_maxlifetime','date.timezone','default_charset'];
        @endphp
        @foreach($directives as $dir)
        <tr>
            <td style="font-family:monospace;">{{ $dir }}</td>
            <td>{{ ini_get($dir) }}</td>
            <td style="color:#777;">{{ ini_get($dir) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.phpinfo.loaded_extensions') }}</strong></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
        @foreach(get_loaded_extensions() as $ext)
        <span style="padding:2px 8px;background:#e9ecef;border-radius:3px;font-family:monospace;font-size:12px;">{{ $ext }}</span>
        @endforeach
        </div>
    </div>
</div>
@endsection
