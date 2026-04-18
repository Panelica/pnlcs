@extends('admin.layouts.app')
@section('title', __('admin.products.create_group'))
@section('content')
<div class="page-header">
    <h1>{{ __('admin.products.create_group') }}</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.groups.store') }}">
            @csrf
            <div class="form-group"><label class="form-label">{{ __('admin.products.group_name') }} <span style="color:#d9534f;">*</span></label><input type="text" name="name" required class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.products.headline') }}</label><input type="text" name="headline" class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.products.tagline') }}</label><input type="text" name="tagline" class="form-control"></div>
            <div style="display:flex;gap:8px;margin-top:10px;">
                <button type="submit" class="btn btn-primary">{{ __('admin.products.create_group') }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
