@extends('admin.layouts.app')
@section('title', 'Create Project')
@section('content')
<div class="page-header">
    <h1>Create Project</h1>
    <a href="{{ route('admin.projects.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.projects.store') }}">
            @csrf
            <div class="form-group"><label class="form-label">Client <span style="color:#d9534f;">*</span></label>
                <select name="client_id" required class="form-control">
                    <option value="">Select a client...</option>
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ old('client_id')==$client->id?'selected':'' }}>{{ $client->full_name }} ({{ $client->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label class="form-label">Title <span style="color:#d9534f;">*</span></label><input type="text" name="title" value="{{ old('title') }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="4" class="form-control">{{ old('description') }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 15px;">
                <div class="form-group"><label class="form-label">Status <span style="color:#d9534f;">*</span></label>
                    <select name="status" required class="form-control">
                        @foreach(['pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status','pending')==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control"></div>
            </div>
            <div style="display:flex;gap:8px;margin-top:5px;">
                <button type="submit" class="btn btn-primary">Create Project</button>
                <a href="{{ route('admin.projects.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
