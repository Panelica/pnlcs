@extends('admin.layouts.app')
@section('title', 'Domain Pricing')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Domain Pricing</h1>
    <button type="button" onclick="openAddTLD()" class="btn btn-primary btn-sm">+ Add TLD</button>
</div>
<div class="card">
    @if($tlds->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No domain pricing configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Extension</th><th>{{ __('common.actions.register') }}</th><th>Transfer</th><th>Renew</th><th>Grace</th><th>Min/Max Yrs</th><th>{{ __('common.table.registrar') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($tlds as $tld)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $tld->extension }}</td>
            <td>${{ number_format($tld->register_price, 2) }}</td>
            <td>${{ number_format($tld->transfer_price, 2) }}</td>
            <td>${{ number_format($tld->renew_price, 2) }}</td>
            <td>{{ $tld->grace_period }}d</td>
            <td>{{ $tld->min_years }}-{{ $tld->max_years }}</td>
            <td>{{ $tld->auto_registrar ?: 'Manual' }}</td>
            <td><span class="badge-{{ $tld->enabled ? 'active' : 'suspended' }}">{{ $tld->enabled ? 'Enabled' : 'Disabled' }}</span></td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs" onclick="openEditTLD({{ json_encode($tld) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.domain-pricing.destroy', $tld) }}" style="display:inline;" onsubmit="return confirm('Delete .{{ $tld->extension }}?')">
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

<div id="modal-tld" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-tld').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:540px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;" id="tld-modal-title">Add TLD</h4>
            <button type="button" onclick="document.getElementById('modal-tld').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="tld-form" action="">
            @csrf
            <span id="tld-method-field"></span>
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Extension</label><input type="text" name="extension" id="tld-ext" required class="form-control" placeholder=".com"></div>
                    <div class="form-group"><label class="form-label">Register Price ($)</label><input type="number" name="register_price" id="tld-reg" step="0.01" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Transfer Price ($)</label><input type="number" name="transfer_price" id="tld-trans" step="0.01" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Renew Price ($)</label><input type="number" name="renew_price" id="tld-ren" step="0.01" required class="form-control"></div>
                    <div class="form-group"><label class="form-label">Grace Period (days)</label><input type="number" name="grace_period" id="tld-grace" value="0" min="0" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Min Years</label><input type="number" name="min_years" id="tld-min" value="1" min="1" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Max Years</label><input type="number" name="max_years" id="tld-max" value="10" min="1" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Auto Registrar</label><input type="text" name="auto_registrar" id="tld-reg2" class="form-control" placeholder="none"></div>
                    <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="tld-sort" value="0" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="enabled" value="1" id="tld-enabled" checked> Enabled</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-tld').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddTLD() {
    document.getElementById('tld-modal-title').textContent = 'Add TLD';
    document.getElementById('tld-form').action = '{{ route("admin.config.domain-pricing.store") }}';
    document.getElementById('tld-method-field').innerHTML = '';
    document.getElementById('tld-ext').value = '';
    document.getElementById('tld-reg').value = '';
    document.getElementById('tld-trans').value = '';
    document.getElementById('tld-ren').value = '';
    document.getElementById('tld-grace').value = 0;
    document.getElementById('tld-min').value = 1;
    document.getElementById('tld-max').value = 10;
    document.getElementById('tld-reg2').value = '';
    document.getElementById('tld-sort').value = 0;
    document.getElementById('tld-enabled').checked = true;
    document.getElementById('modal-tld').style.display = 'flex';
}
function openEditTLD(d) {
    document.getElementById('tld-modal-title').textContent = 'Edit TLD';
    document.getElementById('tld-form').action = '/admin/config/domain-pricing/' + d.id;
    document.getElementById('tld-method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('tld-ext').value = d.extension;
    document.getElementById('tld-reg').value = d.register_price;
    document.getElementById('tld-trans').value = d.transfer_price;
    document.getElementById('tld-ren').value = d.renew_price;
    document.getElementById('tld-grace').value = d.grace_period || 0;
    document.getElementById('tld-min').value = d.min_years || 1;
    document.getElementById('tld-max').value = d.max_years || 10;
    document.getElementById('tld-reg2').value = d.auto_registrar || '';
    document.getElementById('tld-sort').value = d.sort_order || 0;
    document.getElementById('tld-enabled').checked = !!d.enabled;
    document.getElementById('modal-tld').style.display = 'flex';
}
</script>
@endsection
