@extends('admin.layouts.app')
@section('title', 'Products / Services')
@section('content')

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Products / Services</h1>
    <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.products.groups.create') }}" class="btn btn-default btn-sm">+ New Group</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ New Product</a>
    </div>
</div>

@forelse($groups as $group)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <strong>{{ $group->name }}</strong>
        <span style="font-size:12px;color:#777;">{{ $group->products->count() }} product{{ $group->products->count() != 1 ? 's' : '' }}@if($group->headline) &mdash; {{ $group->headline }}@endif</span>
    </div>
    @if($group->products->count() > 0)
    <table class="data-table">
        <thead><tr><th>Product Name</th><th>Type</th><th>Payment</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($group->products as $product)
        <tr>
            <td>
                <a href="{{ route('admin.products.edit', $product) }}" style="color:#337ab7;font-weight:600;">{{ $product->name }}</a>
                @if($product->is_featured) <span style="background:#fcf8e3;color:#8a6d3b;padding:1px 5px;border-radius:3px;font-size:11px;margin-left:4px;">Featured</span>@endif
            </td>
            <td style="text-transform:capitalize;">{{ $product->type }}</td>
            <td style="text-transform:capitalize;">{{ $product->pay_type }}</td>
            <td>
                @if($product->hidden)<span class="badge-suspended">Hidden</span>
                @elseif($product->retired)<span class="badge-terminated">Retired</span>
                @else<span class="badge-active">Active</span>@endif
            </td>
            <td style="text-align:right;">
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-default btn-xs">Edit</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <div class="card-body" style="color:#999;font-size:13px;">No products in this group. <a href="{{ route('admin.products.create') }}" style="color:#337ab7;">Add one</a></div>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;">
        <p style="color:#999;font-size:14px;margin-bottom:12px;">No product groups yet.</p>
        <a href="{{ route('admin.products.groups.create') }}" class="btn btn-primary btn-sm">Create First Group</a>
    </div>
</div>
@endforelse
@endsection
