@extends('admin.layouts.app')
@section('title', __('admin.knowledge_base.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.knowledge_base.title') }}</h1>
    <div style="display:flex;gap:6px;">
        <button type="button" onclick="openModal('add-kb-cat')" class="btn btn-default btn-sm">+ {{ __('admin.knowledge_base.add_category_btn') }}</button>
        <button type="button" onclick="openModal('add-kb-article')" class="btn btn-primary btn-sm">+ {{ __('admin.knowledge_base.new_article') }}</button>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
<div class="card">
    @if(($articles ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.knowledge_base.no_articles') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.form.title') }}</th><th>{{ __('admin.knowledge_base.category') }}</th><th>{{ __('admin.knowledge_base.views') }}</th><th>{{ __('admin.knowledge_base.published') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($articles as $art)
        <tr>
            <td style="font-weight:600;">{{ $art->title }}</td>
            <td>{{ $art->category->name ?? __('admin.knowledge_base.uncategorized') }}</td>
            <td>{{ $art->views ?? 0 }}</td>
            <td><span class="badge-{{ $art->private ? 'draft' : 'active' }}">{{ $art->private ? __('admin.knowledge_base.draft') : __('admin.knowledge_base.published') }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-art-{{ $loop->index }}')" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.knowledge-base.articles.destroy', $art) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.knowledge_base.confirm_delete_article') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
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

<x-modal name="add-kb-cat" title="{{ __('admin.knowledge_base.add_kb_category') }}" maxWidth="sm">
    <form method="POST" action="{{ route('admin.config.knowledge-base.categories.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">{{ __('admin.knowledge_base.category_name') }}</label><input type="text" name="name" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control"></textarea></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-kb-cat')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.knowledge_base.add_category') }}</button>
        </div>
    </form>
</x-modal>

<x-modal name="add-kb-article" title="{{ __('admin.knowledge_base.new_kb_article') }}" maxWidth="xl">
    <form method="POST" action="{{ route('admin.config.knowledge-base.articles.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.knowledge_base.category') }}</label>
            <select name="category_id" class="form-control">
                <option value="">{{ __('admin.knowledge_base.uncategorized_option') }}</option>
                @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label class="form-label">{{ __('admin.knowledge_base.content') }}</label><textarea name="article" rows="8" class="form-control" required></textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="published" value="1" checked>
                <span>{{ __('admin.knowledge_base.publish_immediately') }}</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-kb-article')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.knowledge_base.create_article') }}</button>
        </div>
    </form>
</x-modal>

@foreach($articles ?? [] as $art)
<x-modal :name="'edit-art-' . $loop->index" title="{{ __('admin.knowledge_base.edit_article') }}" maxWidth="xl">
    <form method="POST" action="{{ route('admin.config.knowledge-base.articles.update', $art) }}">
        @csrf @method('PUT')
        <div class="form-group"><label class="form-label">{{ __('common.form.title') }}</label><input type="text" name="title" value="{{ $art->title }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.knowledge_base.category') }}</label>
            <select name="category_id" class="form-control">
                <option value="">{{ __('admin.knowledge_base.uncategorized_option') }}</option>
                @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}" @selected($art->category_id == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label class="form-label">{{ __('admin.knowledge_base.content') }}</label><textarea name="article" rows="8" class="form-control" required>{{ $art->article }}</textarea></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="published" value="0">
                <input type="checkbox" name="published" value="1" @checked(! $art->private)>
                <span>{{ __('admin.knowledge_base.published') }}</span>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-art-{{ $loop->index }}')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
