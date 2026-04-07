@extends('admin.layouts.app')
@section('title', __('admin.logs.email_logs'))
@section('content')

<div class="page-header"><h1>{{ __('admin.logs.system_logs') }}</h1></div>

<div style="border-bottom:2px solid #ddd;margin-bottom:15px;display:flex;">
    @foreach(['admin.logs.index'=>__('admin.logs.activity'),'admin.logs.gateway'=>__('admin.logs.gateway'),'admin.logs.module'=>__('admin.logs.module'),'admin.logs.email'=>__('admin.logs.email')] as $route=>$label)
    <a href="{{ route($route) }}" style="padding:8px 16px;font-size:13px;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;{{ request()->routeIs($route) ? 'border-bottom-color:#337ab7;color:#337ab7;font-weight:600;' : 'color:#555;' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.email') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div><label class="form-label">{{ __('common.form.status') }}</label>
                <select name="status" onchange="this.form.submit()" class="form-control" style="width:140px;">
                    <option value="">{{ __('admin.logs.status_all') }}</option>
                    <option value="sent" {{ request('status')==='sent'?'selected':'' }}>{{ __('common.status.sent') }}</option>
                    <option value="pending" {{ request('status')==='pending'?'selected':'' }}>{{ __('common.status.pending') }}</option>
                    <option value="failed" {{ request('status')==='failed'?'selected':'' }}>{{ __('common.status.failed') }}</option>
                </select>
            </div>
            <div style="flex:1;min-width:200px;"><label class="form-label">{{ __('admin.logs.search_subject_recipient') }}</label><input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('common.placeholder.search') }}" class="form-control"></div>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.filter') }}</button>
                <a href="{{ route('admin.logs.email') }}" class="btn btn-default btn-sm">{{ __('common.actions.clear') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('common.table.client') }}</th><th>{{ __('admin.logs.recipient') }}</th><th>{{ __('common.table.subject') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:#777;">{{ $log->date?->format('Y-m-d H:i:s') ?? $log->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                <td>
                    @if($log->client)<a href="{{ route('admin.clients.show', $log->client) }}" style="color:#337ab7;">{{ $log->client->full_name }}</a>
                    @else<span style="color:#999;">-</span>@endif
                </td>
                <td style="font-size:12px;color:#777;">{{ $log->to ?? '-' }}</td>
                <td>{{ $log->subject ?? '-' }}</td>
                <td>
                    @if($log->failed)<span class="badge-cancelled">{{ __('admin.logs.status_failed') }}</span>
                    @elseif($log->pending)<span class="badge-pending">{{ __('admin.logs.status_pending') }}</span>
                    @else<span class="badge-active">{{ __('admin.logs.status_sent') }}</span>@endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">{{ __('admin.logs.no_email_logs') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())<div style="padding:10px 15px;">{{ $logs->withQueryString()->links() }}</div>@endif
</div>
@endsection
