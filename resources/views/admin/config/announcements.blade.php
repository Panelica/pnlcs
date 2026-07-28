@extends('admin.layouts.app')
@section('title', __('admin.announcements.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.announcements.title') }}</h1>
    <button type="button" onclick="openModal('add-announcement')" class="btn btn-primary btn-sm">+ {{ __('admin.announcements.new_announcement') }}</button>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
<div class="card">
    @if(($announcements ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.announcements.no_announcements_posted') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.announcements.title_column') }}</th><th>{{ __('common.table.date') }}</th><th>{{ __('admin.announcements.published') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($announcements as $ann)
        <tr>
            <td style="font-weight:600;">{{ $ann->title }}</td>
            <td style="font-size:12px;">{{ $ann->date?->format(date_fmt()) ?? $ann->created_at->format(date_fmt()) }}</td>
            <td><span class="badge-{{ $ann->published ? 'active' : 'draft' }}">{{ $ann->published ? __('admin.announcements.status_published') : __('admin.announcements.status_draft') }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-ann-{{ $loop->index }}')" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.announcements.destroy', $ann) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.announcements.confirm_delete") }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($announcements, 'links'))
    <div style="padding:10px 15px;">{{ $announcements->links() }}</div>
    @endif
    @endif
</div>

<x-modal name="add-announcement" title="{{ __('admin.announcements.new_announcement') }}" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.announcements.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.announcements.content') }}</label><textarea name="body" rows="6" class="form-control" required></textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="published" value="1" checked>
                <span>{{ __('admin.announcements.publish_immediately') }}</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-announcement')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.create') }}</button>
        </div>
    </form>
</x-modal>

@foreach($announcements ?? [] as $ann)
<x-modal :name="'edit-ann-' . $loop->index" title="{{ __('admin.announcements.edit_announcement') }}" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.announcements.update', $ann) }}">
        @csrf @method('PUT')
        <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" value="{{ $ann->title }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.announcements.content') }}</label><textarea name="body" rows="6" class="form-control" required>{{ $ann->body }}</textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="published" value="0">
                <input type="checkbox" name="published" value="1" @checked($ann->published)>
                <span>{{ __('admin.announcements.published') }}</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-ann-{{ $loop->index }}')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
