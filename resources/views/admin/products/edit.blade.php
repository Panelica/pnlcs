@extends('admin.layouts.app')
@section('title', 'Edit ' . $product->name)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Edit: {{ $product->name }}</h1>
    <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete product {{ $product->name }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Product</button>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.update', $product) }}">
    @csrf @method('PUT')

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Product Details</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Product Name <span style="color:#d9534f;">*</span></label><input type="text" name="name" value="{{ $product->name }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Product Group</label><select name="group_id" class="form-control">@foreach($groups as $g)<option value="{{ $g->id }}" {{ $product->group_id == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="hosting" {{ $product->type=='hosting'?'selected':'' }}>Shared Hosting</option><option value="reseller" {{ $product->type=='reseller'?'selected':'' }}>Reseller</option><option value="vps" {{ $product->type=='vps'?'selected':'' }}>VPS/Dedicated</option><option value="other" {{ $product->type=='other'?'selected':'' }}>Other</option></select></div>
                <div class="form-group"><label class="form-label">Payment</label><select name="pay_type" class="form-control"><option value="recurring" {{ $product->pay_type=='recurring'?'selected':'' }}>Recurring</option><option value="onetime" {{ $product->pay_type=='onetime'?'selected':'' }}>One Time</option><option value="free" {{ $product->pay_type=='free'?'selected':'' }}>Free</option></select></div>
                <div class="form-group"><label class="form-label">Auto Setup</label><select name="auto_setup" class="form-control"><option value="order" {{ $product->auto_setup=='order'?'selected':'' }}>On Order</option><option value="payment" {{ $product->auto_setup=='payment'?'selected':'' }}>On Payment</option><option value="on" {{ $product->auto_setup=='on'?'selected':'' }}>Always</option><option value="off" {{ $product->auto_setup=='off'?'selected':'' }}>Never</option></select></div>
                <div class="form-group"><label class="form-label">Server Module</label><input type="text" name="server_type" value="{{ $product->server_type }}" placeholder="e.g. panelica, cpanel" class="form-control"></div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control">{{ $product->description }}</textarea></div>
                <div class="form-group" style="grid-column:span 2;display:flex;gap:20px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1" {{ $product->hidden?'checked':'' }}> Hidden</label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="retired" value="1" {{ $product->retired?'checked':'' }}> Retired</label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="is_featured" value="1" {{ $product->is_featured?'checked':'' }}> Featured</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Pricing</strong></div>
        <div class="card-body">
            @foreach($currencies as $currency)
            @php $p = $pricing[$currency->id] ?? null; @endphp
            <div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:15px;margin-bottom:12px;">
                <p style="font-weight:600;font-size:13px;margin:0 0 10px;">{{ $currency->code }} ({{ $currency->prefix }})</p>
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:8px;">
                    @foreach(['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cycle)
                    <div>
                        <label class="form-label" style="text-transform:capitalize;font-size:11px;">{{ $cycle }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="{{ $p ? $p->$cycle : -1 }}" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                    @foreach(['monthly_setup','quarterly_setup','semiannually_setup','annually_setup'] as $setup)
                    <div>
                        <label class="form-label" style="font-size:11px;">{{ str_replace('_', ' ', ucfirst($setup)) }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $setup }}]" value="{{ $p ? $p->$setup : 0 }}" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-default">Cancel</a>
    </div>
</form>
@endsection
