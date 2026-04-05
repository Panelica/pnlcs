@extends('admin.layouts.app')
@section('title', 'Email Templates')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Email Templates</h1>
</div>
<div class="card">
    @if(($templates ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No email templates found.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Template Name</th><th>{{ __('common.table.subject') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($templates as $tpl)
        <tr>
            <td style="font-weight:600;">{{ $tpl->name }}</td>
            <td style="font-size:12px;color:#555;">{{ $tpl->subject }}</td>
            <td style="text-transform:capitalize;">{{ $tpl->type ?? 'general' }}</td>
            <td><span class="badge-{{ $tpl->disabled ? 'suspended' : 'active' }}">{{ $tpl->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-tpl-{{ $loop->index }}')" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@foreach($templates ?? [] as $tpl)
<x-modal :name="'edit-tpl-' . $loop->index" title="Edit Email Template" maxWidth="xl">
    <form method="POST" action="{{ route('admin.config.email-templates.update', $tpl) }}">
        @csrf @method('PUT')
        <div class="form-group"><label class="form-label">Template Name</label><input type="text" name="name" value="{{ $tpl->name }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('common.form.subject') }}</label><input type="text" name="subject" value="{{ $tpl->subject }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control">
                <option value="general" @selected(($tpl->type ?? 'general') === 'general')>General</option>
                <option value="service" @selected($tpl->type === 'service')>Service</option>
                <option value="domain" @selected($tpl->type === 'domain')>Domain</option>
                <option value="invoice" @selected($tpl->type === 'invoice')>Invoice</option>
                <option value="support" @selected($tpl->type === 'support')>Support</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Body (HTML)</label>
            <textarea name="message" rows="10" class="form-control" style="font-family:monospace;font-size:12px;">{{ $tpl->message }}</textarea>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="disabled" value="0">
                <input type="checkbox" name="disabled" value="1" @checked($tpl->disabled)>
                <span>Disabled</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-tpl-{{ $loop->index }}')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Template</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
