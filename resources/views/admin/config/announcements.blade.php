@extends('admin.layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Announcements</h1>
    <a href="{{ route('admin.config.announcements.create') }}" class="btn btn-primary btn-sm">+ New Announcement</a>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

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
                <a href="{{ route('admin.config.announcements.edit', $ann) }}" class="btn btn-default btn-xs">Edit</a>
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
@endsection
