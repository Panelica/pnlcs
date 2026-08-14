@extends('admin.layouts.app')
@section('title', __('admin.custom_fields.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.custom_fields.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-field').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.custom_fields.add_field') }}</button>
</div>
<div class="card">
    @if(($customFields ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.custom_fields.no_fields') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.custom_fields.name_col') }}</th><th>{{ __('admin.custom_fields.type_col') }}</th><th>{{ __('admin.custom_fields.flags_col') }}</th><th>{{ __('admin.custom_fields.order_col') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($customFields as $field)
        <tr>
            <td style="font-weight:600;">{{ $field->field_name }}</td>
            <td>{{ __('admin.custom_fields.type_' . $field->field_type) }}</td>
            <td style="font-size:12px;color:#666;">
                @if($field->required){{ __('admin.custom_fields.required') }}@endif
                @if($field->admin_only){{ $field->required ? ', ' : '' }}{{ __('admin.custom_fields.admin_only') }}@endif
                @if($field->show_on_invoice){{ ($field->required || $field->admin_only) ? ', ' : '' }}{{ __('admin.custom_fields.show_on_invoice') }}@endif
                @if($field->show_on_order){{ ($field->required || $field->admin_only || $field->show_on_invoice) ? ', ' : '' }}{{ __('admin.custom_fields.show_on_order') }}@endif
            </td>
            <td>{{ $field->sort_order }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditField({{ json_encode(['id'=>$field->id,'field_name'=>$field->field_name,'field_type'=>$field->field_type,'description'=>$field->description,'field_options'=>$field->field_options,'regex'=>$field->regex,'required'=>(bool)$field->required,'admin_only'=>(bool)$field->admin_only,'show_on_invoice'=>(bool)$field->show_on_invoice,'show_on_order'=>(bool)$field->show_on_order,'sort_order'=>$field->sort_order]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.custom-fields.destroy', $field) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.custom_fields.confirm_delete') }}')">
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

<div id="modal-add-field" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-field').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:560px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.custom_fields.add_field') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-field').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.custom-fields.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.field_name') }} <span style="color:#d9534f;">*</span></label><input type="text" name="field_name" required class="form-control" placeholder="NIP"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.custom_fields.field_type') }}</label>
                        <select name="field_type" class="form-control">
                            @foreach(['text','textarea','select','checkbox','number','date'] as $t)
                            <option value="{{ $t }}" {{ old('field_type') == $t ? 'selected' : '' }}>{{ __('admin.custom_fields.type_' . $t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">{{ __('admin.custom_fields.sort_order') }}</label><input type="number" name="sort_order" value="0" min="0" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.field_options') }}</label><textarea name="field_options" rows="3" class="form-control" placeholder="Option 1&#10;Option 2"></textarea><small style="color:#777;font-size:12px;">{{ __('admin.custom_fields.field_options_hint') }}</small></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.regex') }}</label><input type="text" name="regex" class="form-control" placeholder="/\d{10}/"></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.description') }}</label><input type="text" name="description" class="form-control"></div>
                </div>
                <div style="margin-top:8px;display:flex;gap:18px;font-size:13px;">
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="required" value="1"> {{ __('admin.custom_fields.required') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="admin_only" value="1"> {{ __('admin.custom_fields.admin_only') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="show_on_order" value="1"> {{ __('admin.custom_fields.show_on_order') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="show_on_invoice" value="1"> {{ __('admin.custom_fields.show_on_invoice') }}</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-field').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.custom_fields.save_field') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-field" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-field').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:560px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.custom_fields.edit_field') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-field').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-field-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.field_name') }}</label><input type="text" name="field_name" id="ef-name" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.custom_fields.field_type') }}</label>
                        <select name="field_type" id="ef-type" class="form-control">
                            @foreach(['text','textarea','select','checkbox','number','date'] as $t)
                            <option value="{{ $t }}">{{ __('admin.custom_fields.type_' . $t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">{{ __('admin.custom_fields.sort_order') }}</label><input type="number" name="sort_order" id="ef-order" min="0" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.field_options') }}</label><textarea name="field_options" id="ef-options" rows="3" class="form-control"></textarea></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.regex') }}</label><input type="text" name="regex" id="ef-regex" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('admin.custom_fields.description') }}</label><input type="text" name="description" id="ef-desc" class="form-control"></div>
                </div>
                <div style="margin-top:8px;display:flex;gap:18px;font-size:13px;">
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="required" id="ef-required" value="1"> {{ __('admin.custom_fields.required') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="admin_only" id="ef-admin-only" value="1"> {{ __('admin.custom_fields.admin_only') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="show_on_order" id="ef-show-order" value="1"> {{ __('admin.custom_fields.show_on_order') }}</label>
                    <label style="display:flex;align-items:center;gap:5px;"><input type="checkbox" name="show_on_invoice" id="ef-show-invoice" value="1"> {{ __('admin.custom_fields.show_on_invoice') }}</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-field').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditField(d) {
    document.getElementById('edit-field-form').action = '/admin/config/custom-fields/' + d.id;
    document.getElementById('ef-name').value = d.field_name;
    document.getElementById('ef-type').value = d.field_type;
    document.getElementById('ef-order').value = d.sort_order;
    document.getElementById('ef-options').value = d.field_options || '';
    document.getElementById('ef-regex').value = d.regex || '';
    document.getElementById('ef-desc').value = d.description || '';
    document.getElementById('ef-required').checked = !!d.required;
    document.getElementById('ef-admin-only').checked = !!d.admin_only;
    document.getElementById('ef-show-order').checked = !!d.show_on_order;
    document.getElementById('ef-show-invoice').checked = !!d.show_on_invoice;
    document.getElementById('modal-edit-field').style.display = 'flex';
}
</script>
@endsection