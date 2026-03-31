@extends('admin.layouts.app')
@section('title', 'System Database')
@section('content')

<div class="page-header">
    <h1>System Database</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
    <div class="stat-card">
        <div class="stat-value">{{ $tableCount ?? '?' }}</div>
        <div class="stat-label">Total Tables</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $dbSize ?? '?' }}</div>
        <div class="stat-label">Database Size</div>
    </div>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>Database Actions</strong></div>
    <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.config.system-database.optimize') }}" onsubmit="return confirm('Optimize all tables? This may take a moment.')">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Optimize Tables</button>
        </form>
        <form method="POST" action="{{ route('admin.config.system-database.repair') }}" onsubmit="return confirm('Run repair on all tables?')">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">Repair Tables</button>
        </form>
        <a href="{{ route('admin.config.system-database.backup') }}" class="btn btn-default btn-sm">Download Backup</a>
    </div>
</div>

@if(isset($tables) && count($tables) > 0)
<div class="card">
    <div class="card-header"><strong>Table Status</strong></div>
    <table class="data-table">
        <thead><tr><th>Table Name</th><th>Engine</th><th>Rows</th><th>Data Size</th><th>Index Size</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($tables as $table)
        <tr>
            <td style="font-family:monospace;">{{ $table->Name }}</td>
            <td>{{ $table->Engine }}</td>
            <td>{{ number_format($table->Rows) }}</td>
            <td>{{ number_format($table->Data_length / 1024, 1) }} KB</td>
            <td>{{ number_format($table->Index_length / 1024, 1) }} KB</td>
            <td><span class="badge-{{ strtolower($table->Comment ?? 'active') === 'ok' || empty($table->Comment) ? 'active' : 'open' }}">{{ $table->Comment ?: 'OK' }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
