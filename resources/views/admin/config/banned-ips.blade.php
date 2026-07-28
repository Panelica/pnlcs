@extends('admin.layouts.app')
@section('title', __('admin.banned_ips.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.banned_ips.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-ip').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.banned_ips.ban_ip') }}</button>
</div>
<div class="card">
    @if(($bannedIps ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.banned_ips.no_banned') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.ip_address') }}</th><th>{{ __('admin.banned_ips.reason') }}</th><th>{{ __('admin.banned_ips.banned_on') }}</th><th>{{ __('admin.banned_ips.expires') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($bannedIps as $ban)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $ban->ip }}</td>
            <td style="font-size:13px;">{{ $ban->reason ?: '&mdash;' }}</td>
            <td style="font-size:12px;">{{ $ban->created_at->timezone(display_tz())->format(datetime_fmt()) }}</td>
            <td style="font-size:12px;">{{ $ban->expires_at?->timezone(display_tz())->format(datetime_fmt()) ?? __('admin.banned_ips.never') }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.banned-ips.destroy', $ban) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.banned_ips.confirm_unban") }} {{ $ban->ip }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-default btn-xs">{{ __('admin.banned_ips.unban') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-ip" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-ip').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.banned_ips.ban_ip') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-ip').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.banned-ips.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.banned_ips.ip_address') }}</label><input type="text" name="ip" required class="form-control" placeholder="192.168.1.1"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.banned_ips.reason') }}</label><input type="text" name="reason" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-ip').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.banned_ips.ban_ip') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
