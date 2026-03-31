@extends('admin.layouts.app')
@section('title', 'API Credentials')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>API Credentials</h1>
    <button type="button" onclick="document.getElementById('modal-add-api').style.display='flex'" class="btn btn-primary btn-sm">+ Generate API Key</button>
</div>

@if(session('new_secret'))
<div style="padding:15px;background:#fcf8e3;border:1px solid #faebcc;border-radius:4px;margin-bottom:15px;">
    <strong style="color:#8a6d3b;">Save this API Secret now — it will NOT be shown again:</strong>
    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
        <code id="api-secret-display" style="flex:1;padding:10px;background:#fff;border:1px solid #ddd;border-radius:3px;font-size:14px;word-break:break-all;">{{ session('new_secret') }}</code>
        <button type="button" class="btn btn-default btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('api-secret-display').textContent);this.textContent='Copied!';">Copy</button>
    </div>
</div>
@endif

<p style="color:#666;font-size:13px;margin-bottom:15px;">API credentials allow external applications to interact with PNLCS via the WHMCS-compatible API. Each key has a unique identifier and secret for authentication.</p>

<div class="card">
    @if(($credentials ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No API keys configured. Generate one to enable API access.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Description</th><th>API Identifier</th><th>Admin</th><th>Created</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($credentials as $cred)
        <tr>
            <td style="font-weight:600;">{{ $cred->description ?: 'No description' }}</td>
            <td><code style="font-size:12px;background:#f5f5f5;padding:2px 6px;border-radius:3px;">{{ $cred->identifier }}</code></td>
            <td style="font-size:12px;">{{ $cred->admin->full_name ?? 'N/A' }}</td>
            <td style="font-size:12px;color:#777;">{{ $cred->created_at?->format('d M Y H:i') ?? '-' }}</td>
            <td><span class="badge {{ $cred->active ? 'badge-active' : 'badge-suspended' }}">{{ $cred->active ? 'Active' : 'Disabled' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.api-credentials.destroy', $cred) }}" style="display:inline;" onsubmit="return confirm('Revoke this API key? This cannot be undone.')">
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

<div style="margin-top:15px;padding:12px 15px;background:#d9edf7;border:1px solid #bce8f1;border-radius:4px;color:#31708f;font-size:13px;">
    <strong>API Usage:</strong> Send POST requests to <code>/api/v1/{action}</code> with parameters <code>identifier</code> and <code>secret</code>.
    @if(Route::has('admin.api-docs'))
    <a href="{{ route('admin.api-docs') }}" style="color:#337ab7;">View API Documentation</a>
    @endif
</div>

{{-- Generate Modal --}}
<div id="modal-add-api" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Generate API Credential</h4>
            <button type="button" onclick="this.closest('[id]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.api-credentials.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <input type="text" name="description" required class="form-control" placeholder="e.g. Mobile App, External CRM">
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Generate</button>
            </div>
        </form>
    </div>
</div>
@endsection
