@extends('admin.layouts.app')
@section('title', __('admin.system_database.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('admin.system_database.title') }}</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
    <div class="stat-card">
        <div class="stat-value">{{ $tableCount ?? '?' }}</div>
        <div class="stat-label">{{ __('admin.system_database.total_tables') }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $dbSize ?? '?' }}</div>
        <div class="stat-label">{{ __('admin.system_database.database_size') }}</div>
    </div>
</div>

@if(isset($tables) && count($tables) > 0)
<div class="card">
    <div class="card-header"><strong>{{ __('admin.system_database.table_status') }}</strong></div>
    <table class="data-table">
        <thead><tr><th>{{ __('admin.system_database.table_name') }}</th><th>{{ __('admin.system_database.engine') }}</th><th>{{ __('admin.system_database.rows') }}</th><th>{{ __('admin.system_database.data_size') }}</th><th>{{ __('admin.system_database.index_size') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
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
