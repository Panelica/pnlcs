@extends('admin.layouts.app')
@section('title', 'Knowledge Base')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Knowledge Base</h1>
    <div style="display:flex;gap:6px;">
        <button type="button" onclick="document.getElementById('modal-add-cat').style.display='flex'" class="btn btn-default btn-sm">+ Category</button>
        <a href="{{ route('admin.config.knowledge-base.create') }}" class="btn btn-primary btn-sm">+ New Article</a>
    </div>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($articles ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No knowledge base articles yet.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Title</th><th>Category</th><th>Views</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($articles as $art)
        <tr>
            <td style="font-weight:600;">{{ $art->title }}</td>
            <td>{{ $art->category->name ?? 'Uncategorized' }}</td>
            <td>{{ $art->views ?? 0 }}</td>
            <td><span class="badge-{{ $art->published ? 'active' : 'draft' }}">{{ $art->published ? 'Published' : 'Draft' }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.config.knowledge-base.edit', $art) }}" class="btn btn-default btn-xs">Edit</a>
                <form method="POST" action="{{ route('admin.config.knowledge-base.destroy', $art) }}" style="display:inline;" onsubmit="return confirm('Delete this article?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($articles, 'links'))
    <div style="padding:10px 15px;">{{ $articles->links() }}</div>
    @endif
    @endif
</div>

<div id="modal-add-cat" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-cat').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:400px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add KB Category</h4>
            <button type="button" onclick="document.getElementById('modal-add-cat').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.knowledge-base.categories.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Category Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-cat').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Category</button>
            </div>
        </form>
    </div>
</div>
@endsection
