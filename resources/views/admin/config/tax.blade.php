@extends('admin.layouts.app')
@section('title', __('admin.tax.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.tax.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-tax').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.tax.add_rule') }}</button>
</div>
<div class="card">
    @if(($taxes ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.tax.no_rules') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.tax.tax_name_col') }}</th><th>{{ __('admin.tax.rate_col') }}</th><th>{{ __('common.table.country') }}</th><th>{{ __('common.table.state') }}</th><th>{{ __('admin.tax.level') }}</th><th>{{ __('admin.tax.compound') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($taxes as $tax)
        <tr>
            <td style="font-weight:600;">{{ $tax->name }}</td>
            <td>{{ $tax->rate }}%</td>
            <td>{{ $tax->country ?: __('admin.tax.all') }}</td>
            <td>{{ $tax->state ?: __('admin.tax.all') }}</td>
            <td>{{ $tax->level == 1 ? __('admin.tax.tax1') : __('admin.tax.tax2') }}</td>
            <td>{{ $tax->compound ? __('admin.tax.yes') : __('admin.tax.no') }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditTax({{ json_encode(['id'=>$tax->id,'name'=>$tax->name,'rate'=>$tax->rate,'country'=>$tax->country,'state'=>$tax->state,'level'=>$tax->level,'compound'=>$tax->compound]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.tax.destroy', $tax) }}" style="display:inline;" onsubmit="return confirm('{{ __(\"admin.tax.confirm_delete\") }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-tax" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-tax').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.tax.add_rule') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-tax').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.tax.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.tax.tax_name') }}</label><input type="text" name="name" required class="form-control" placeholder="VAT"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.rate_col') }}</label><input type="number" name="rate" step="0.01" required class="form-control" placeholder="20"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.level') }}</label><select name="level" class="form-control"><option value="1">{{ __('admin.tax.tax1_primary') }}</option><option value="2">{{ __('admin.tax.tax2_secondary') }}</option></select></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.country_hint') }} <small style="color:#999;">{{ __('admin.tax.blank_all') }}</small></label><input type="text" name="country" class="form-control" placeholder="GB"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.state_hint') }} <small style="color:#999;">{{ __('admin.tax.blank_all') }}</small></label><input type="text" name="state" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="compound" value="1"> {{ __('admin.tax.compound_tax') }}</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-tax').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.tax.add_rule') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-tax" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-tax').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.tax.edit_rule') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-tax').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-tax-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.tax.tax_name') }}</label><input type="text" name="name" id="et-name" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.rate_col') }}</label><input type="number" name="rate" id="et-rate" step="0.01" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.level') }}</label><select name="level" id="et-level" class="form-control"><option value="1">{{ __('admin.tax.tax1') }}</option><option value="2">{{ __('admin.tax.tax2') }}</option></select></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.country') }}</label><input type="text" name="country" id="et-country" class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.tax.state_hint') }}</label><input type="text" name="state" id="et-state" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="compound" value="1" id="et-compound"> {{ __('admin.tax.compound_tax') }}</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-tax').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTax(d) {
    document.getElementById('edit-tax-form').action = '/admin/config/tax/' + d.id;
    document.getElementById('et-name').value = d.name;
    document.getElementById('et-rate').value = d.rate;
    document.getElementById('et-level').value = d.level;
    document.getElementById('et-country').value = d.country || '';
    document.getElementById('et-state').value = d.state || '';
    document.getElementById('et-compound').checked = !!d.compound;
    document.getElementById('modal-edit-tax').style.display = 'flex';
}
</script>
@endsection
