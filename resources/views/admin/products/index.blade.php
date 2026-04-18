@extends('admin.layouts.app')
@section('title', __('admin.products.title'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.products.title') }}</h1>
    <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.products.groups.create') }}" class="btn btn-default btn-sm">+ {{ __('admin.products.new_group') }}</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.products.new_product') }}</a>
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
        <thead><tr><th>{{ __('admin.products.product_name') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('admin.products.payment') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($group->products as $product)
        <tr>
            <td>
                <a href="{{ route('admin.products.edit', $product) }}" style="color:#337ab7;font-weight:600;">{{ $product->name }}</a>
                @if($product->is_featured) <span style="background:#fcf8e3;color:#8a6d3b;padding:1px 5px;border-radius:3px;font-size:11px;margin-left:4px;">{{ __('admin.products.featured') }}</span>@endif
            </td>
            <td style="text-transform:capitalize;">{{ $product->type }}</td>
            <td style="text-transform:capitalize;">{{ $product->pay_type }}</td>
            <td>
                @if($product->hidden)<span class="badge-suspended">{{ __('admin.products.hidden') }}</span>
                @elseif($product->retired)<span class="badge-terminated">{{ __('admin.products.retired') }}</span>
                @else<span class="badge-active">{{ __('admin.products.active') }}</span>@endif
            </td>
            <td style="text-align:right;">
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <div class="card-body" style="color:#999;font-size:13px;">{{ __('admin.products.no_products_in_group') }} <a href="{{ route('admin.products.create') }}" style="color:#337ab7;">{{ __('admin.products.add_one') }}</a></div>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;">
        <p style="color:#999;font-size:14px;margin-bottom:12px;">{{ __('admin.products.no_groups') }}</p>
        <a href="{{ route('admin.products.groups.create') }}" class="btn btn-primary btn-sm">{{ __('admin.products.create_first_group') }}</a>
    </div>
</div>
@endforelse
@endsection
