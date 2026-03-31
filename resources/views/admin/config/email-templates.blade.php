@extends('admin.layouts.app')
@section('title', 'Email Templates')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Email Templates</h1>
    <button type="button" onclick="document.getElementById('modal-add-et').style.display='flex'" class="btn btn-primary btn-sm">+ Create Template</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($emailTemplates ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No email templates configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Template Name</th><th>Subject</th><th>Type</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($emailTemplates as $tpl)
        <tr>
            <td style="font-weight:600;">{{ $tpl->name }}</td>
            <td style="font-size:12px;color:#555;">{{ $tpl->subject }}</td>
            <td style="text-transform:capitalize;">{{ $tpl->type ?? 'general' }}</td>
            <td><span class="badge-{{ $tpl->disabled ? 'suspended' : 'active' }}">{{ $tpl->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.config.email-templates.edit', $tpl) }}" class="btn btn-default btn-xs">Edit</a>
                <form method="POST" action="{{ route('admin.config.email-templates.destroy', $tpl) }}" style="display:inline;" onsubmit="return confirm('Delete this template?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-et" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-et').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Create Email Template</h4>
            <button type="button" onclick="document.getElementById('modal-add-et').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.email-templates.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Template Name</label><input type="text" name="name" required class="form-control" placeholder="Welcome Email"></div>
                <div class="form-group"><label class="form-label">Subject</label><input type="text" name="subject" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="general">General</option><option value="service">Service</option><option value="domain">Domain</option><option value="invoice">Invoice</option><option value="support">Support</option></select></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-et').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Template</button>
            </div>
        </form>
    </div>
</div>
@endsection
