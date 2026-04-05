@extends('admin.layouts.app')
@section('title', __('admin.projects.edit_project'))
@section('content')
<div class="page-header">
    <h1>{{ __('admin.projects.edit_project') }}</h1>
    <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.projects.update', $project) }}">
            @csrf @method('PUT')
            <div class="form-group"><label class="form-label">{{ __('common.form.title') }} <span style="color:#d9534f;">*</span></label><input type="text" name="title" value="{{ old('title',$project->title) }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="4" class="form-control">{{ old('description',$project->description) }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 15px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.status') }} <span style="color:#d9534f;">*</span></label>
                    <select name="status" required class="form-control">
                        @foreach(['pending'=>__('common.status.pending'),'in_progress'=>__('common.status.in_progress'),'completed'=>__('common.status.completed'),'cancelled'=>__('common.status.cancelled')] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status',$project->status)==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.projects.start_date') }}</label><input type="date" name="start_date" value="{{ old('start_date', $project->start_date ? \Carbon\Carbon::parse($project->start_date)->toDateString() : '') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.projects.due_date') }}</label><input type="date" name="due_date" value="{{ old('due_date', $project->due_date ? \Carbon\Carbon::parse($project->due_date)->toDateString() : '') }}" class="form-control"></div>
            </div>
            <div style="display:flex;gap:8px;margin-top:5px;">
                <button type="submit" class="btn btn-primary">{{ __('admin.projects.update_project') }}</button>
                <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
