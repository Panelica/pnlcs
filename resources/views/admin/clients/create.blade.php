@extends('admin.layouts.app')
@section('title', 'Add Client')
@section('content')
<div class="page-header">
    <h1>Add New Client</h1>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.first_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="first_name" value="{{ old('first_name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.last_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="last_name" value="{{ old('last_name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.email') }}<span style="color:#d9534f;">*</span></label><input type="email" name="email" value="{{ old('email') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Company</label><input type="text" name="company_name" value="{{ old('company_name') }}" class="form-control"></div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('common.form.address') }}</label><input type="text" name="address1" value="{{ old('address1') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.city') }}</label><input type="text" name="city" value="{{ old('city') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">State</label><input type="text" name="state" value="{{ old('state') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Postcode</label><input type="text" name="postcode" value="{{ old('postcode') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" value="{{ old('country', 'US') }}" maxlength="2" class="form-control"></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone_number" value="{{ old('phone_number') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option><option value="closed">Closed</option></select>
                </div>
                <div class="form-group"><label class="form-label">Group</label>
                    <select name="group_id" class="form-control"><option value="">None</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select>
                </div>
                <div class="form-group"><label class="form-label">Currency</label>
                    <select name="currency_id" class="form-control">@foreach($currencies as $c)<option value="{{ $c->id }}" {{ $c->is_default ? 'selected' : '' }}>{{ $c->code }} ({{ $c->prefix }})</option>@endforeach</select>
                </div>
            </div>
            <div style="margin-top:10px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Create Client</button>
                <a href="{{ route('admin.clients.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
