@extends('admin.layouts.app')
@section('title', 'Downloads')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Downloads</h1>
    <div style="display:flex;gap:6px;">
        <button type="button" onclick="document.getElementById('modal-add-dlcat').style.display='flex'" class="btn btn-default btn-sm">+ Category</button>
        <button type="button" onclick="document.getElementById('modal-add-dl').style.display='flex'" class="btn btn-primary btn-sm">+ Add Download</button>
    </div>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

@if(($categories ?? collect())->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#999;">No download categories yet. Add a category to organize downloads.</div></div>
@else
@foreach($categories as $category)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <strong>{{ $category->name }}</strong>
        <div style="display:flex;gap:6px;">
            <form method="POST" action="{{ route('admin.config.downloads.categories.destroy', $category) }}" onsubmit="return confirm('Delete category and all its downloads?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-xs">Delete Category</button>
            </form>
        </div>
    </div>
    @if(($category->downloads ?? collect())->isEmpty())
    <div class="card-body" style="color:#999;font-size:13px;">No downloads in this category.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Filename</th><th>Description</th><th>Size</th><th>Downloads</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($category->downloads as $dl)
        <tr>
            <td style="font-weight:600;">{{ $dl->name }}</td>
            <td style="font-size:12px;color:#555;">{{ Str::limit($dl->description, 60) }}</td>
            <td style="font-size:12px;">{{ $dl->size ?? '-' }}</td>
            <td>{{ $dl->downloads ?? 0 }}</td>
            <td><span class="badge-{{ $dl->published ? 'active' : 'draft' }}">{{ $dl->published ? 'Published' : 'Draft' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.downloads.destroy', $dl) }}" style="display:inline;" onsubmit="return confirm('Delete this download?')">
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
@endforeach
@endif

<div id="modal-add-dlcat" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-dlcat').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:400px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Category</h4>
            <button type="button" onclick="document.getElementById('modal-add-dlcat').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.downloads.categories.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Category Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-dlcat').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Category</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-add-dl" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-dl').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Download</h4>
            <button type="button" onclick="document.getElementById('modal-add-dl').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.downloads.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Category</label>
                    <select name="category_id" required class="form-control">
                        @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
                <div class="form-group"><label class="form-label">File URL or Upload</label><input type="text" name="url" class="form-control" placeholder="https://..."></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="published" value="1" checked> Published</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-dl').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Download</button>
            </div>
        </form>
    </div>
</div>
@endsection
