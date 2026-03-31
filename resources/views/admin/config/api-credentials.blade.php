@extends('admin.layouts.app')
@section('title', 'API Credentials')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>API Credentials</h1>
    <button type="button" onclick="document.getElementById('modal-add-api').style.display='flex'" class="btn btn-primary btn-sm">+ Generate API Key</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($credentials ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No API keys configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Name</th><th>API Key</th><th>Permissions</th><th>Created</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($credentials as $credential)
        <tr>
            <td style="font-weight:600;">{{ $credential->name }}</td>
            <td><span style="font-family:monospace;font-size:12px;background:#f5f5f5;padding:2px 6px;border-radius:3px;">{{ substr($credential->api_key, 0, 8) }}...{{ substr($credential->api_key, -4) }}</span></td>
            <td style="font-size:12px;">{{ $credential->permissions ?? 'All' }}</td>
            <td style="font-size:12px;color:#777;">{{ $credential->created_at->format('d M Y') }}</td>
            <td><span class="badge-{{ $credential->disabled ? 'suspended' : 'active' }}">{{ $credential->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.api-credentials.destroy', $credential) }}" style="display:inline;" onsubmit="return confirm('Revoke this API key?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Revoke</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-api" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-api').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Generate API Key</h4>
            <button type="button" onclick="document.getElementById('modal-add-api').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.api-credentials.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Key Name / Description</label><input type="text" name="name" required class="form-control" placeholder="e.g. Mobile App Integration"></div>
                <div class="form-group"><label class="form-label">IP Whitelist <small style="color:#999;">(optional, comma-separated)</small></label><input type="text" name="ip_whitelist" class="form-control" placeholder="192.168.1.1, 10.0.0.0/8"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-api').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Generate Key</button>
            </div>
        </form>
    </div>
</div>
@endsection
