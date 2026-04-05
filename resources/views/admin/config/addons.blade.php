@extends("admin.layouts.app")
@section("title", __("admin.product_addons"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1><i class="fas fa-puzzle-piece"></i> Product Addons</h1>
    <button type="button" onclick="document.getElementById('modal-add-addon').style.display='flex'" class="btn btn-primary btn-sm">+ Add Addon</button>
</div>

<div class="card">
    @if($addons->isEmpty())
        <div class="card-body" style="text-align:center;padding:40px;color:#999;">No product addons configured. Click "Add Addon" to create one.</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>{{ __('common.table.description') }}</th>
            <th>Visibility</th>
            <th>{{ __('common.table.status') }}</th>
            <th>Sort</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($addons as $addon)
        <tr>
            <td style="font-weight:600;">{{ $addon->name }}</td>
            <td style="font-size:12px;color:#777;">{{ \Illuminate\Support\Str::limit($addon->description ?? '', 60) ?: '-' }}</td>
            <td>
                @if($addon->hidden)
                    <span class="badge badge-open">Hidden</span>
                @else
                    <span class="badge badge-active">Visible</span>
                @endif
            </td>
            <td>
                @if($addon->retired)
                    <span class="badge badge-pending">Retired</span>
                @else
                    <span class="badge badge-active">Active</span>
                @endif
            </td>
            <td>{{ $addon->sort_order }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs" onclick="editAddon({{ $addon->id }}, {{ json_encode($addon) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.addons.destroy', $addon->id) }}" style="display:inline;" onsubmit="return confirm('Delete this addon?')">
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

{{-- Add Addon Modal --}}
<div id="modal-add-addon" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-addon').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Product Addon</h4>
            <button type="button" onclick="document.getElementById('modal-add-addon').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.addons.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" required class="form-control" placeholder="e.g. Extra IP Address"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control" placeholder="Addon description"></textarea></div>
                <div class="form-group"><label class="form-label">Applicable Products</label>
                    <select name="packages[]" multiple class="form-control" style="height:100px;">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <p style="font-size:11px;color:#999;margin-top:4px;">Hold Ctrl/Cmd to select multiple. Leave empty for all products.</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">Sort Order</label><input type="number" name="sort_order" value="0" class="form-control"></div>
                </div>
                <div class="form-group" style="display:flex;gap:16px;">
                    <label class="form-label"><input type="checkbox" name="hidden" value="1"> Hidden</label>
                    <label class="form-label"><input type="checkbox" name="tax" value="1" checked> Apply Tax</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-addon').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Addon</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Addon Modal --}}
<div id="modal-edit-addon" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-addon').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Addon</h4>
            <button type="button" onclick="document.getElementById('modal-edit-addon').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form id="edit-addon-form" method="POST" action="">
            @csrf @method("PUT")
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" id="edit-addon-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" id="edit-addon-desc" rows="2" class="form-control"></textarea></div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="edit-addon-sort" class="form-control"></div>
                </div>
                <div class="form-group" style="display:flex;gap:16px;">
                    <label class="form-label"><input type="checkbox" name="hidden" value="1" id="edit-addon-hidden"> Hidden</label>
                    <label class="form-label"><input type="checkbox" name="retired" value="1" id="edit-addon-retired"> Retired</label>
                    <label class="form-label"><input type="checkbox" name="tax" value="1" id="edit-addon-tax"> Apply Tax</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-addon').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.update') }}</button>
            </div>
        </form>
    </div>
</div>

@push("scripts")
<script>
function editAddon(id, data) {
    document.getElementById('edit-addon-form').action = '/admin/config/addons/' + id;
    document.getElementById('edit-addon-name').value = data.name || '';
    document.getElementById('edit-addon-desc').value = data.description || '';
    document.getElementById('edit-addon-sort').value = data.sort_order || 0;
    document.getElementById('edit-addon-hidden').checked = !!data.hidden;
    document.getElementById('edit-addon-retired').checked = !!data.retired;
    document.getElementById('edit-addon-tax').checked = !!data.tax;
    document.getElementById('modal-edit-addon').style.display = 'flex';
}
</script>
@endpush
@endsection
