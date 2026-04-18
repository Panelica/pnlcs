@extends('admin.layouts.app')
@section('title', __('admin.logs.title'))
@section('content')

<div class="page-header"><h1>{{ __('admin.logs.title') }}</h1></div>

{{-- Tab Navigation --}}
<div style="border-bottom:2px solid #ddd;margin-bottom:15px;display:flex;">
    @foreach(['admin.logs.index'=>__('admin.logs.activity'),'admin.logs.gateway'=>__('admin.logs.gateway'),'admin.logs.module'=>__('admin.logs.module'),'admin.logs.email'=>__('admin.logs.email')] as $route=>$label)
    <a href="{{ route($route) }}" style="padding:8px 16px;font-size:13px;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;{{ request()->routeIs($route) ? 'border-bottom-color:#337ab7;color:#337ab7;font-weight:600;' : 'color:#555;' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div><label class="form-label">{{ __('admin.logs.admin_user') }}</label><input type="text" name="user" value="{{ request('user') }}" placeholder="{{ __('admin.logs.filter_user_placeholder') }}" class="form-control" style="width:180px;"></div>
            <div><label class="form-label">{{ __('admin.logs.date_label') }}</label><input type="date" name="date" value="{{ request('date') }}" class="form-control"></div>
            <div style="flex:1;min-width:200px;"><label class="form-label">{{ __('common.actions.search') }}</label><input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.logs.search_description') }}" class="form-control"></div>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.filter') }}</button>
                <a href="{{ route('admin.logs.index') }}" class="btn btn-default btn-sm">{{ __('common.actions.clear') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('admin.logs.user_label') }}</th><th>{{ __('common.table.description') }}</th><th>{{ __('common.table.ip_address') }}</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:#777;">{{ $log->date?->format('Y-m-d H:i:s') ?? '-' }}</td>
                <td><span style="background:#e8eef7;color:#337ab7;padding:2px 8px;border-radius:3px;font-size:12px;">{{ $log->user ?: 'System' }}</span></td>
                <td>{{ $log->description }}</td>
                <td style="font-family:monospace;font-size:12px;color:#777;">{{ $log->ip_address ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">{{ __('admin.logs.no_activity_logs') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())<div style="padding:10px 15px;">{{ $logs->withQueryString()->links() }}</div>@endif
</div>
@endsection
