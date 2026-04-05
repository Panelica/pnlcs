@extends('admin.layouts.app')
@section('title', 'Currencies')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Currencies</h1>
    <button type="button" onclick="document.getElementById('modal-add-currency').style.display='flex'" class="btn btn-primary btn-sm">+ Add Currency</button>
</div>
<div class="card">
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.code') }}</th><th>{{ __('common.table.name') }}</th><th>Prefix</th><th>Suffix</th><th>{{ __('common.table.rate') }}</th><th>Default</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($currencies as $currency)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $currency->code }}</td>
            <td>{{ $currency->prefix }}</td>
            <td style="font-family:monospace;">{{ $currency->prefix }}</td>
            <td style="font-family:monospace;">{{ $currency->suffix ?? '' }}</td>
            <td>{{ $currency->rate }}</td>
            <td>{{ $currency->default ? '<span class="badge-active">Default</span>' : '' }}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditCurrency({{ json_encode(['id'=>$currency->id,'code'=>$currency->code,'prefix'=>$currency->prefix,'suffix'=>$currency->suffix,'rate'=>$currency->rate,'default'=>$currency->default]) }})">{{ __('common.actions.edit') }}</button>
                @if(!$currency->default)
                <form method="POST" action="{{ route('admin.config.currencies.destroy', $currency) }}" style="display:inline;" onsubmit="return confirm('Delete currency {{ $currency->code }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div id="modal-add-currency" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-currency').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Currency</h4>
            <button type="button" onclick="document.getElementById('modal-add-currency').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.currencies.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label class="form-label">Currency Code</label><input type="text" name="code" required maxlength="3" class="form-control" placeholder="USD"></div>
                    <div class="form-group"><label class="form-label">Exchange Rate</label><input type="number" name="rate" step="0.000001" required value="1" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Prefix (symbol)</label><input type="text" name="prefix" class="form-control" placeholder="$"></div>
                    <div class="form-group"><label class="form-label">Suffix</label><input type="text" name="suffix" class="form-control"></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-currency').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Currency</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-currency" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-currency').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Currency</h4>
            <button type="button" onclick="document.getElementById('modal-edit-currency').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-currency-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label class="form-label">Currency Code</label><input type="text" name="code" id="ec-code" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Exchange Rate</label><input type="number" name="rate" id="ec-rate" step="0.000001" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Prefix</label><input type="text" name="prefix" id="ec-prefix" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Suffix</label><input type="text" name="suffix" id="ec-suffix" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="default" value="1" id="ec-default"> Set as Default Currency</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-currency').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCurrency(d) {
    document.getElementById('edit-currency-form').action = '/admin/config/currencies/' + d.id;
    document.getElementById('ec-code').value = d.code;
    document.getElementById('ec-rate').value = d.rate;
    document.getElementById('ec-prefix').value = d.prefix || '';
    document.getElementById('ec-suffix').value = d.suffix || '';
    document.getElementById('ec-default').checked = !!d.default;
    document.getElementById('modal-edit-currency').style.display = 'flex';
}
</script>
@endsection
