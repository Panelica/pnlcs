@extends("admin.layouts.app")
@section("title", __("admin.product_addons"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1><i class="fas fa-puzzle-piece"></i> {{ __('admin.addons.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-addon').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.addons.add_addon') }}</button>
</div>

<div class="card">
    @if($addons->isEmpty())
        <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.addons.no_addons') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>{{ __('common.table.description') }}</th>
            <th>{{ __('admin.addons.visibility') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th>{{ __('admin.addons.monthly_price') }}</th>
            <th>{{ __('admin.addons.sort') }}</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($addons as $addon)
        <tr>
            <td style="font-weight:600;">{{ $addon->name }}</td>
            <td style="font-size:12px;color:#777;">{{ \Illuminate\Support\Str::limit($addon->description ?? '', 60) ?: '-' }}</td>
            <td>
                @if($addon->hidden)
                    <span class="badge badge-open">{{ __('admin.addons.hidden') }}</span>
                @else
                    <span class="badge badge-active">{{ __('admin.addons.visible') }}</span>
                @endif
            </td>
            <td>
                @if($addon->retired)
                    <span class="badge badge-pending">{{ __('admin.addons.retired') }}</span>
                @else
                    <span class="badge badge-active">{{ __('admin.addons.active') }}</span>
                @endif
            </td>
            <td>{{ number_format($addon->priceFor('monthly'), 2) }}</td>
            <td>{{ $addon->sort_order }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs" onclick="editAddon({{ $addon->id }}, {{ json_encode($addon) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.addons.destroy', $addon->id) }}" style="display:inline;" onsubmit="return pnConfirm(event, @js(__('admin.addons.confirm_delete')))">
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
    <div style="position:relative;background:#fff;border-radius:4px;width:680px;max-width:96%;max-height:90vh;overflow:auto;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:18px 24px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:18px;">{{ __('admin.addons.add_product_addon') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-addon').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.addons.store') }}">
            @csrf
            <div style="padding:24px;">
                <div class="form-group"><label class="form-label">{{ __('admin.addons.name') }} *</label><input type="text" name="name" required class="form-control" placeholder="{{ __('admin.addons.name_placeholder') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="3" class="form-control" placeholder="{{ __('admin.addons.description_placeholder') }}"></textarea></div>
                <div class="form-group"><label class="form-label">{{ __('admin.addons.applicable_products') }}</label>
                    <div style="max-height:180px;overflow:auto;border:1px solid #d9d9d9;border-radius:6px;padding:10px 12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 16px;">
                        @foreach($products as $product)
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#333;margin:0;cursor:pointer;">
                                <input type="checkbox" name="packages[]" value="{{ $product->id }}"> {{ $product->name }}
                            </label>
                        @endforeach
                    </div>
                    <p style="font-size:12px;color:#666;background:#f7f7f7;border:1px solid #eee;border-radius:4px;padding:8px 10px;margin-top:6px;">{{ __('admin.addons.applicable_products_hint') }}</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.addons.sort_order') }}</label><input type="number" name="sort_order" value="0" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.addons.pricing') }}</label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_monthly') }}</label><input type="number" step="0.01" min="-1" name="pricing[monthly]" value="0" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_quarterly') }}</label><input type="number" step="0.01" min="-1" name="pricing[quarterly]" value="0" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_semiannually') }}</label><input type="number" step="0.01" min="-1" name="pricing[semiannually]" value="0" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_annually') }}</label><input type="number" step="0.01" min="-1" name="pricing[annually]" value="0" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_biennially') }}</label><input type="number" step="0.01" min="-1" name="pricing[biennially]" value="0" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_triennially') }}</label><input type="number" step="0.01" min="-1" name="pricing[triennially]" value="0" class="form-control"></div>
                    </div>
                    <p style="font-size:12px;color:#666;background:#f7f7f7;border:1px solid #eee;border-radius:4px;padding:8px 10px;margin-top:6px;">{{ __('admin.addons.pricing_hint') }}</p>
                </div>

                <div class="form-group" style="display:flex;gap:20px;align-items:center;">
                    <label class="form-label"><input type="checkbox" name="hidden" value="1"> {{ __('admin.addons.hidden') }}</label>
                    <label class="form-label"><input type="checkbox" name="tax" value="1" checked> {{ __('admin.addons.apply_tax') }}</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-addon').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.addons.create_addon') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Addon Modal --}}
<div id="modal-edit-addon" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-addon').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:680px;max-width:96%;max-height:90vh;overflow:auto;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:18px 24px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:18px;">{{ __('admin.addons.edit_addon') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-addon').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form id="edit-addon-form" method="POST" action="">
            @csrf @method("PUT")
            <div style="padding:24px;">
                <div class="form-group"><label class="form-label">{{ __('admin.addons.name') }} *</label><input type="text" name="name" id="edit-addon-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" id="edit-addon-desc" rows="3" class="form-control"></textarea></div>
                <div class="form-group"><label class="form-label">{{ __('admin.addons.applicable_products') }}</label>
                    <div id="edit-addon-packages" style="max-height:180px;overflow:auto;border:1px solid #d9d9d9;border-radius:6px;padding:10px 12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 16px;">
                        @foreach($products as $product)
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#333;margin:0;cursor:pointer;">
                                <input type="checkbox" name="packages[]" value="{{ $product->id }}"> {{ $product->name }}
                            </label>
                        @endforeach
                    </div>
                    <p style="font-size:12px;color:#666;background:#f7f7f7;border:1px solid #eee;border-radius:4px;padding:8px 10px;margin-top:6px;">{{ __('admin.addons.applicable_products_hint') }}</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.addons.sort_order') }}</label><input type="number" name="sort_order" id="edit-addon-sort" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.addons.pricing') }}</label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_monthly') }}</label><input type="number" step="0.01" min="-1" name="pricing[monthly]" value="0" id="edit-addon-price-monthly" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_quarterly') }}</label><input type="number" step="0.01" min="-1" name="pricing[quarterly]" value="0" id="edit-addon-price-quarterly" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_semiannually') }}</label><input type="number" step="0.01" min="-1" name="pricing[semiannually]" value="0" id="edit-addon-price-semiannually" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_annually') }}</label><input type="number" step="0.01" min="-1" name="pricing[annually]" value="0" id="edit-addon-price-annually" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_biennially') }}</label><input type="number" step="0.01" min="-1" name="pricing[biennially]" value="0" id="edit-addon-price-biennially" class="form-control"></div>
                        <div><label style="font-size:12px;color:#444;font-weight:600;display:block;margin-bottom:4px;">{{ __('admin.addons.cycle_triennially') }}</label><input type="number" step="0.01" min="-1" name="pricing[triennially]" value="0" id="edit-addon-price-triennially" class="form-control"></div>
                    </div>
                    <p style="font-size:12px;color:#666;background:#f7f7f7;border:1px solid #eee;border-radius:4px;padding:8px 10px;margin-top:6px;">{{ __('admin.addons.pricing_hint') }}</p>
                </div>

                <div class="form-group" style="display:flex;gap:20px;align-items:center;">
                    <label class="form-label"><input type="checkbox" name="hidden" value="1" id="edit-addon-hidden"> {{ __('admin.addons.hidden') }}</label>
                    <label class="form-label"><input type="checkbox" name="retired" value="1" id="edit-addon-retired"> {{ __('admin.addons.retired') }}</label>
                    <label class="form-label"><input type="checkbox" name="tax" value="1" id="edit-addon-tax"> {{ __('admin.addons.apply_tax') }}</label>
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
    var packages = String(data.packages || '').split(',').filter(Boolean);
    Array.prototype.forEach.call(document.querySelectorAll('#edit-addon-packages input[type="checkbox"]'), function (box) {
        box.checked = packages.indexOf(String(box.value)) !== -1;
    });
    var pricing = (data.pricing && data.pricing[0]) || {};
    ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'].forEach(function (cycle) {
        document.getElementById('edit-addon-price-' + cycle).value = pricing[cycle] || 0;
    });
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
