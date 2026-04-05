@extends('admin.layouts.app')
@section('title', 'Activity Log')
@section('content')

<div class="page-header">
    <h1>Activity Log</h1>
</div>

<div class="card">
    @if(($logs ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No activity log entries.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Date/Time</th><th>Admin</th><th>{{ __('common.table.ip_address') }}</th><th>{{ __('common.table.description') }}</th></tr></thead>
        <tbody>
        @foreach($logs as $log)
        <tr>
            <td style="white-space:nowrap;font-size:12px;">{{ $log->created_at->format('d M Y H:i:s') }}</td>
            <td>{{ $log->user ?? $log->admin ?? '-' }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $log->ip_address ?? $log->ip ?? '-' }}</td>
            <td>{{ $log->description ?? $log->action }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($logs, 'links'))
    <div style="padding:10px 15px;">{{ $logs->links() }}</div>
    @endif
    @endif
</div>
@endsection
