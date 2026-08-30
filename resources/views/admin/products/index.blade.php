@extends('admin.layouts.app')
@section('title', __('admin.products.title'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.products.title') }}</h1>
    <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.products.groups.create') }}" class="btn btn-default btn-sm">+ {{ __('admin.products.new_group') }}</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.products.new_product') }}</a>
        <button type="button" onclick="openAddCatalog()" class="btn btn-success btn-sm">+ {{ __('admin.products.add_product_service') }}</button>
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

<div class="card" style="margin-bottom:15px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <strong>{{ __('admin.products.products_services') }}</strong>
        <span style="font-size:12px;color:#777;">{{ $invoiceProducts->count() }} {{ __('admin.products.products_services') }}</span>
    </div>
    @if($invoiceProducts->count() > 0)
    <table class="data-table">
        <thead><tr><th>{{ __('admin.products.product_name') }}</th><th style="text-align:right;">{{ __('admin.invoices.price') }}</th><th>{{ __('admin.products.unit') }}</th><th>{{ __('admin.invoices.tax') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($invoiceProducts as $p)
        <tr>
            <td style="font-weight:600;">{{ $p->name }}</td>
            <td style="text-align:right;font-family:monospace;white-space:nowrap;">{{ money_fmt($p->price) }}</td>
            <td>{{ $p->unit ?: '-' }}</td>
            <td>{{ $p->tax_label ?: rtrim(rtrim(number_format((float) $p->tax_rate, 2), '0'), '.').'%' }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick='openEditCatalog({{ json_encode(['id'=>$p->id,'name'=>$p->name,'price'=>(float)$p->price,'unit'=>$p->unit,'tax_label'=>$p->tax_label]) }})'>{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.products.catalog.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.products.confirm_delete_product_service') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <div class="card-body" style="color:#999;font-size:13px;">{{ __('admin.products.no_products_services') }}</div>
    @endif
</div>

<div id="modal-catalog" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-catalog').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:440px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 id="catalog-title" style="margin:0;font-size:16px;">{{ __('admin.products.add_product_service') }}</h4>
            <button type="button" onclick="document.getElementById('modal-catalog').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="catalog-form" action="{{ route('admin.products.catalog.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cf-method" value="POST">
            <div style="padding:20px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.products.product_name') }}</label>
                    <input type="text" name="name" id="cf-name" required class="form-control" placeholder="Usługa informatyczna">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.invoices.price') }}</label>
                        <input type="number" name="price" id="cf-price" step="0.01" required class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.products.unit') }}</label>
                        <input type="text" name="unit" id="cf-unit" class="form-control" placeholder="szt. / godz.">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.invoices.tax') }}</label>
                    <select name="tax_label" id="cf-tax" class="form-control">
                        <option value="">0%</option>
                        @foreach($taxRateOptions as $r)
                        <option value="{{ $r->name }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-catalog').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
const defaultTaxLabel = @json($defaultTaxLabel);
function openAddCatalog() {
    document.getElementById('catalog-title').textContent = '{{ __('admin.products.add_product_service') }}';
    document.getElementById('catalog-form').action = '{{ route('admin.products.catalog.store') }}';
    document.getElementById('cf-method').value = 'POST';
    document.getElementById('cf-name').value = '';
    document.getElementById('cf-price').value = '';
    document.getElementById('cf-unit').value = '';
    document.getElementById('cf-tax').value = defaultTaxLabel;
    document.getElementById('modal-catalog').style.display = 'flex';
}
function openEditCatalog(d) {
    document.getElementById('catalog-title').textContent = '{{ __('common.actions.edit') }}';
    document.getElementById('catalog-form').action = '/admin/products/catalog/' + d.id;
    document.getElementById('cf-method').value = 'PUT';
    document.getElementById('cf-name').value = d.name;
    document.getElementById('cf-price').value = d.price;
    document.getElementById('cf-unit').value = d.unit || '';
    document.getElementById('cf-tax').value = d.tax_label || '';
    document.getElementById('modal-catalog').style.display = 'flex';
}
</script>
@endsection
