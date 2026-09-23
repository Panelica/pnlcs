@extends("admin.layouts.app")
@section("title", __("admin.live_servers.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.live_servers.title') }}</h1>
    <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ __('admin.live_servers.description') }}</p>
</div>

<div class="card">
    @if($servers->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:var(--pn-muted);">
        <p style="margin:0 0 12px;">{{ __('admin.live_servers.none') }}</p>
        <a href="{{ route('admin.config.servers') }}" class="btn btn-primary btn-sm">+ {{ __('admin.live_servers.add_server') }}</a>
    </div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>{{ __('admin.servers.hostname') }}</th>
            <th>{{ __('common.table.ip_address') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($servers as $server)
        <tr>
            <td style="font-weight:600;">{{ $server->name }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->hostname }}</td>
            <td style="font-family:monospace;font-size:12px;">{{ $server->ip_address ?: '-' }}</td>
            <td><span class="badge {{ $server->active ? 'badge-active' : 'badge-suspended' }}">{{ $server->active ? __('common.status.active') : __('common.status.disabled') }}</span></td>
            <td style="text-align:right;">
                {{-- A new tab: the panel is a different site, and the list
                     should still be here when the operator comes back. --}}
                <form method="POST" action="{{ route('admin.live-servers.login', $server) }}" target="_blank" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-xs">{{ __('admin.live_servers.login') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@endsection
