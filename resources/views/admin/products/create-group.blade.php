@extends('admin.layouts.app')
@section('title', isset($group) ? __('admin.products.edit_group') : __('admin.products.create_group'))
@section('content')
@php $editing = isset($group); @endphp
<div class="page-header">
    <h1>{{ $editing ? __('admin.products.edit_group') : __('admin.products.create_group') }}</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; {{ __('common.actions.back') }}</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $editing ? route('admin.products.groups.update', $group) : route('admin.products.groups.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-group"><label class="form-label">{{ __('admin.products.group_name') }} <span style="color:#d9534f;">*</span></label><input type="text" name="name" required class="form-control" value="{{ old('name', $group->name ?? '') }}"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.products.headline') }}</label><input type="text" name="headline" class="form-control" value="{{ old('headline', $group->headline ?? '') }}"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.products.tagline') }}</label><input type="text" name="tagline" class="form-control" value="{{ old('tagline', $group->tagline ?? '') }}"></div>
            @if($editing)
            <div class="form-group">
                <label style="font-weight:400;display:flex;gap:6px;align-items:center;"><input type="checkbox" name="hidden" value="1" @checked(old('hidden', $group->hidden))> {{ __('admin.products.hidden') }}</label>
                <small style="display:block;color:#777;margin-top:4px;">{{ __('admin.products.group_hidden_hint') }}</small>
            </div>
            @endif
            <div style="display:flex;gap:8px;margin-top:10px;">
                <button type="submit" class="btn btn-primary">{{ $editing ? __('common.actions.save_changes') : __('admin.products.create_group') }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
