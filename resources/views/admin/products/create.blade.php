@extends('admin.layouts.app')
@section('title', 'Create Product')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Create Product</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>

@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.products.store') }}">
    @csrf
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Product Details</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Product Name <span style="color:#d9534f;">*</span></label><input type="text" name="name" value="{{ old('name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Product Group <span style="color:#d9534f;">*</span></label><select name="group_id" required class="form-control">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">Product Type <span style="color:#d9534f;">*</span></label><select name="type" class="form-control"><option value="hosting">Shared Hosting</option><option value="reseller">Reseller Hosting</option><option value="vps">VPS/Dedicated</option><option value="other">Other</option></select></div>
                <div class="form-group"><label class="form-label">Payment Type <span style="color:#d9534f;">*</span></label><select name="pay_type" class="form-control"><option value="recurring">Recurring</option><option value="onetime">One Time</option><option value="free">Free</option></select></div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Pricing</strong></div>
        <div class="card-body">
            @foreach($currencies as $currency)
            <div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:15px;margin-bottom:12px;">
                <p style="font-weight:600;font-size:13px;margin:0 0 10px;">{{ $currency->code }} ({{ $currency->prefix }})</p>
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;">
                    @foreach(['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cycle)
                    <div>
                        <label class="form-label" style="text-transform:capitalize;">{{ $cycle }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="-1" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
                <p style="font-size:11px;color:#999;margin:6px 0 0;">Set -1 to disable a billing cycle</p>
            </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">Create Product</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-default">Cancel</a>
    </div>
</form>
@endsection
