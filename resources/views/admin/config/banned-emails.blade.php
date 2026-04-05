@extends('admin.layouts.app')
@section('title', 'Banned Emails')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Banned Emails</h1>
    <button type="button" onclick="document.getElementById('modal-add-be').style.display='flex'" class="btn btn-primary btn-sm">+ Ban Email</button>
</div>
<div class="card">
    @if(($bannedEmails ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No banned emails or domains.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Email / Domain</th><th>Reason</th><th>Banned On</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($bannedEmails as $ban)
        <tr>
            <td style="font-family:monospace;">{{ $ban->email }}</td>
            <td style="font-size:13px;">{{ $ban->reason ?: '&mdash;' }}</td>
            <td style="font-size:12px;">{{ $ban->created_at->format('d M Y') }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.banned-emails.destroy', $ban) }}" style="display:inline;" onsubmit="return confirm('Remove ban?')">
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
            <h4 style="margin:0;font-size:16px;">Ban Email / Domain</h4>
            <button type="button" onclick="document.getElementById('modal-add-be').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.banned-emails.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Email or Domain</label><input type="text" name="email" required class="form-control" placeholder="spam@example.com or @example.com"></div>
                <div class="form-group"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-be').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-danger btn-sm">Ban Email</button>
            </div>
        </form>
    </div>
</div>
@endsection
