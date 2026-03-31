@extends('admin.layouts.app')
@section('title', 'PHP Info')
@section('content')

<div class="page-header">
    <h1>PHP Information</h1>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:15px;">
    <div class="stat-card"><div class="stat-value">{{ PHP_VERSION }}</div><div class="stat-label">PHP Version</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('memory_limit') }}</div><div class="stat-label">Memory Limit</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('max_execution_time') }}s</div><div class="stat-label">Max Exec Time</div></div>
    <div class="stat-card"><div class="stat-value">{{ ini_get('upload_max_filesize') }}</div><div class="stat-label">Upload Limit</div></div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>PHP Configuration</strong></div>
    <table class="data-table">
        <thead><tr><th>Directive</th><th>Local Value</th><th>Master Value</th></tr></thead>
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
    <div class="card-header"><strong>Loaded Extensions</strong></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
        @foreach(get_loaded_extensions() as $ext)
        <span style="padding:2px 8px;background:#e9ecef;border-radius:3px;font-family:monospace;font-size:12px;">{{ $ext }}</span>
        @endforeach
        </div>
    </div>
</div>
@endsection
