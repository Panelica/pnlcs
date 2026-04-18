@extends('admin.layouts.app')
@section('title', __('admin.logs.gateway_logs'))
@section('content')

<div class="page-header"><h1>{{ __('admin.logs.system_logs') }}</h1></div>

<div style="border-bottom:2px solid #ddd;margin-bottom:15px;display:flex;">
    @foreach(['admin.logs.index'=>__('admin.logs.activity'),'admin.logs.gateway'=>__('admin.logs.gateway'),'admin.logs.module'=>__('admin.logs.module'),'admin.logs.email'=>__('admin.logs.email')] as $route=>$label)
    <a href="{{ route($route) }}" style="padding:8px 16px;font-size:13px;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;{{ request()->routeIs($route) ? 'border-bottom-color:#337ab7;color:#337ab7;font-weight:600;' : 'color:#555;' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.gateway') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div><label class="form-label">{{ __('admin.logs.gateway') }}</label>
                <select name="gateway" onchange="this.form.submit()" class="form-control" style="width:160px;">
                    <option value="">{{ __('admin.logs.all_gateways') }}</option>
                    @foreach($gateways as $gw)<option value="{{ $gw }}" {{ request('gateway')===$gw?'selected':'' }}>{{ ucfirst($gw) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">{{ __('common.form.date') }}</label><input type="date" name="date" value="{{ request('date') }}" class="form-control"></div>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.filter') }}</button>
                <a href="{{ route('admin.logs.gateway') }}" class="btn btn-default btn-sm">{{ __('common.actions.clear') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('admin.logs.gateway') }}</th><th>{{ __('admin.logs.data_request') }}</th><th>{{ __('admin.logs.result') }}</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:#777;">{{ $log->date ? \Carbon\Carbon::parse($log->date)->format('Y-m-d H:i:s') : '-' }}</td>
                <td style="font-weight:600;text-transform:capitalize;">{{ ucfirst($log->gateway ?? '-') }}</td>
                <td style="font-family:monospace;font-size:12px;color:#777;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->data ? Str::limit($log->data, 120) : '-' }}</td>
                <td>
                    @if($log->result==='success')<span class="badge-active">{{ __('common.status.success') }}</span>
                    @elseif(in_array($log->result,['error','failed']))<span class="badge-cancelled">{{ __('common.status.failed') }}</span>
                    @else<span style="font-size:12px;color:#777;">{{ $log->result ?? '-' }}</span>@endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">{{ __('admin.logs.no_gateway_logs') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())<div style="padding:10px 15px;">{{ $logs->withQueryString()->links() }}</div>@endif
</div>
@endsection
