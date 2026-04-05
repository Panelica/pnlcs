@extends("admin.layouts.app")
@section("title", __("admin.product_bundles"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1><i class="fas fa-layer-group"></i> Product Bundles</h1>
    <button type="button" onclick="document.getElementById('modal-add-bundle').style.display='flex'" class="btn btn-primary btn-sm">+ Create Bundle</button>
</div>

<div class="card">
    @if($bundles->isEmpty())
        <div class="card-body" style="text-align:center;padding:40px;color:#999;">No product bundles configured. Click "Create Bundle" to add one.</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>Products</th>
            <th>{{ __('common.table.discount') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($bundles as $bundle)
        <tr>
            <td>
                <div style="font-weight:600;">{{ $bundle->name }}</div>
                @if($bundle->description)
                    <div style="font-size:11px;color:#777;">{{ \Illuminate\Support\Str::limit($bundle->description, 60) }}</div>
                @endif
            </td>
            <td>
                @foreach($bundle->items as $item)
                    @if($item->product)
                        <span class="badge badge-open" style="margin-bottom:2px;">{{ $item->product->name }}{{ $item->qty > 1 ? ' x'.$item->qty : '' }}</span>
                    @else
                        <span class="badge badge-pending" style="margin-bottom:2px;">Item #{{ $item->item_id }} ({{ $item->item_type }})</span>
                    @endif
                @endforeach
                @if($bundle->items->isEmpty())
                    <span style="color:#999;font-size:12px;">No products</span>
                @endif
            </td>
            <td>
                @if($bundle->discount_type === 'percentage')
                    <span style="font-weight:600;color:#5cb85c;">{{ number_format($bundle->discount_value, 0) }}% off</span>
                @else
                    <span style="font-weight:600;color:#5cb85c;">${{ number_format($bundle->discount_value, 2) }} off</span>
                @endif
            </td>
            <td>
                @if($bundle->is_active)
                    <span class="badge badge-active">Active</span>
                @else
                    <span class="badge badge-open">Inactive</span>
                @endif
            </td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.bundles.destroy', $bundle->id) }}" style="display:inline;" onsubmit="return confirm('Delete this bundle and all its items?')">
                    @csrf @method("DELETE")
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- Create Bundle Modal --}}
<div id="modal-add-bundle" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-bundle').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:540px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Create Product Bundle</h4>
            <button type="button" onclick="document.getElementById('modal-add-bundle').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.bundles.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Bundle Name *</label><input type="text" name="name" required class="form-control" placeholder="e.g. Starter Pack"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control" placeholder="Optional bundle description"></textarea></div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Discount Type *</label>
                        <select name="discount_type" required class="form-control">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount ($)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Discount Value *</label>
                        <input type="number" name="discount_value" required step="0.01" min="0" class="form-control" placeholder="10">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Products * (min 2)</label>
                    <div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                        @foreach($products as $product)
                        <label style="display:block;padding:4px 0;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}">
                            {{ $product->name }}
                            @if($product->group)
                                <span style="color:#999;font-size:11px;">({{ $product->group->name }})</span>
                            @endif
                        </label>
                        @endforeach
                    </div>
                    @if($products->isEmpty())
                        <p style="color:#999;font-size:12px;margin-top:4px;">No products available. Create products first.</p>
                    @endif
                </div>
                <div class="form-group">
                    <label class="form-label"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-bundle').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Bundle</button>
            </div>
        </form>
    </div>
</div>
@endsection
