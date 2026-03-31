@extends('admin.layouts.app')
@section('title', 'Knowledge Base')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Knowledge Base</h1>
    <div style="display:flex;gap:6px;">
        <button type="button" onclick="openModal('add-kb-cat')" class="btn btn-default btn-sm">+ Category</button>
        <button type="button" onclick="openModal('add-kb-article')" class="btn btn-primary btn-sm">+ New Article</button>
    </div>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
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
                <button type="button" onclick="openModal('edit-art-{{ $loop->index }}')" class="btn btn-default btn-xs">Edit</button>
                <form method="POST" action="{{ route('admin.config.knowledge-base.articles.destroy', $art) }}" style="display:inline;" onsubmit="return confirm('Delete this article?')">
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

<x-modal name="add-kb-cat" title="Add KB Category" maxWidth="sm">
    <form method="POST" action="{{ route('admin.config.knowledge-base.categories.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">Category Name</label><input type="text" name="name" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-kb-cat')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Add Category</button>
        </div>
    </form>
</x-modal>

<x-modal name="add-kb-article" title="New KB Article" maxWidth="xl">
    <form method="POST" action="{{ route('admin.config.knowledge-base.articles.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Category</label>
            <select name="kbcategory_id" class="form-control">
                <option value="">— Uncategorized —</option>
                @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label class="form-label">Content</label><textarea name="body" rows="8" class="form-control" required></textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="published" value="1" checked>
                <span>Publish immediately</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-kb-article')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Create Article</button>
        </div>
    </form>
</x-modal>

@foreach($articles ?? [] as $art)
<x-modal :name="'edit-art-' . $loop->index" title="Edit Article" maxWidth="xl">
    <form method="POST" action="{{ route('admin.config.knowledge-base.articles.update', $art) }}">
        @csrf @method('PUT')
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" value="{{ $art->title }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">Category</label>
            <select name="kbcategory_id" class="form-control">
                <option value="">— Uncategorized —</option>
                @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}" @selected($art->kbcategory_id == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label class="form-label">Content</label><textarea name="body" rows="8" class="form-control" required>{{ $art->body }}</textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="published" value="0">
                <input type="checkbox" name="published" value="1" @checked($art->published)>
                <span>Published</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-art-{{ $loop->index }}')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
