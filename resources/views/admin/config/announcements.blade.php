@extends('admin.layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Announcements</h1>
    <button type="button" onclick="openModal('add-announcement')" class="btn btn-primary btn-sm">+ New Announcement</button>
</div>
<div class="card">
    @if(($announcements ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No announcements posted.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Title</th><th>Date</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($announcements as $ann)
        <tr>
            <td style="font-weight:600;">{{ $ann->title }}</td>
            <td style="font-size:12px;">{{ $ann->date?->format('d M Y') ?? $ann->created_at->format('d M Y') }}</td>
            <td><span class="badge-{{ $ann->published ? 'active' : 'draft' }}">{{ $ann->published ? 'Published' : 'Draft' }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-ann-{{ $loop->index }}')" class="btn btn-default btn-xs">Edit</button>
                <form method="POST" action="{{ route('admin.config.announcements.destroy', $ann) }}" style="display:inline;" onsubmit="return confirm('Delete this announcement?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
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

<x-modal name="add-announcement" title="New Announcement" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.announcements.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" value="{{ now()->toDateString() }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Content</label><textarea name="body" rows="6" class="form-control" required></textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="published" value="1" checked>
                <span>Publish immediately</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-announcement')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Create</button>
        </div>
    </form>
</x-modal>

@foreach($announcements ?? [] as $ann)
<x-modal :name="'edit-ann-' . $loop->index" title="Edit Announcement" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.announcements.update', $ann) }}">
        @csrf @method('PUT')
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" value="{{ $ann->title }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" value="{{ $ann->date?->format('Y-m-d') ?? $ann->created_at->format('Y-m-d') }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Content</label><textarea name="body" rows="6" class="form-control" required>{{ $ann->body }}</textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="published" value="0">
                <input type="checkbox" name="published" value="1" @checked($ann->published)>
                <span>Published</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-ann-{{ $loop->index }}')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
