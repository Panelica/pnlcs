@extends('admin.layouts.app')
@section('title', __('admin.downloads.edit_download'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.downloads.edit_download') }}</h1>
    <a href="{{ route('admin.config.downloads') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.downloads.title') }}</a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card" style="max-width:640px;">
    <form method="POST" action="{{ route('admin.config.downloads.update', $download) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="card-body">
            <div class="form-group"><label class="form-label">{{ __('common.form.name') }}</label><input type="text" name="title" value="{{ old('title', $download->title) }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.downloads.category') }}</label>
                <select name="category_id" required class="form-control">
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $download->category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="3" class="form-control">{{ old('description', $download->description) }}</textarea></div>
            <div class="form-group">
                <label class="form-label">{{ $download->isStoredFile() ? __('admin.downloads.replace_file') : __('admin.downloads.upload_file') }}</label>
                @if($download->isStoredFile())
                <div style="font-size:12px;font-family:monospace;margin-bottom:6px;">{{ $download->fileName() }}</div>
                @endif
                <input type="file" name="file" class="form-control">
            </div>
            <div class="form-group"><label class="form-label">{{ __('admin.downloads.or_link') }}</label><input type="text" name="location" value="{{ old('location', $download->isStoredFile() ? '' : $download->location) }}" class="form-control">
                <p style="font-size:11px;color:#999;margin-top:4px;">{{ __('admin.downloads.file_or_link_hint') }}</p>
            </div>
            <div class="form-group"><label class="form-label">{{ __('admin.downloads.products') }}</label>
                @php $chosen = (array) old('products', $download->products->pluck('id')->all()); @endphp
                <select name="products[]" multiple class="form-control" style="height:110px;">
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(in_array($p->id, $chosen))>{{ $p->name }}</option>
                    @endforeach
                </select>
                <p style="font-size:11px;color:#999;margin-top:4px;">{{ __('admin.downloads.products_hint') }}</p>
            </div>
            <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="published" value="1" @checked(old('published', ! $download->hidden))> {{ __('admin.downloads.published') }}</label></div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
            <a href="{{ route('admin.config.downloads') }}" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
        </div>
    </form>
</div>
@endsection
