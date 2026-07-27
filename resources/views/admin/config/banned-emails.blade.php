@extends('admin.layouts.app')
@section('title', __('admin.banned_emails.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.banned_emails.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-be').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.banned_emails.ban_email') }}</button>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
<div class="card">
    @if(($bannedEmails ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.banned_emails.no_banned') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.banned_emails.email_domain') }}</th><th>{{ __('admin.banned_emails.reason') }}</th><th>{{ __('admin.banned_emails.banned_on') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($bannedEmails as $ban)
        <tr>
            <td style="font-family:monospace;">{{ $ban->email }}</td>
            <td style="font-size:13px;">{{ $ban->reason ?: '&mdash;' }}</td>
            <td style="font-size:12px;">{{ $ban->created_at->format('d M Y') }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.banned-emails.destroy', $ban) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.banned_emails.confirm_remove") }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-default btn-xs">{{ __('common.actions.remove') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-be" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-be').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.banned_emails.ban_email_domain') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-be').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.banned-emails.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.banned_emails.email_domain') }}</label><input type="text" name="domain" required class="form-control" placeholder="{{ __('admin.banned_emails.placeholder') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.banned_emails.reason') }}</label><input type="text" name="reason" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-be').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.banned_emails.ban_email') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
