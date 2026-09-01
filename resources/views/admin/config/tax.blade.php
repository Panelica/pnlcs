@extends('admin.layouts.app')
@section('title', __('admin.tax.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.tax.title') }}</h1>
    <button type="button" onclick="openAddTax()" class="btn btn-primary btn-sm">+ {{ __('admin.tax.add_rule') }}</button>
</div>

<div class="card">
    @if(($groups ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.tax.no_rules') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.tax.country') }}</th><th>{{ __('admin.tax.state') }}</th><th>{{ __('admin.tax.default_rate') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($groups as $g)
        <tr>
            <td style="font-weight:600;">{{ $g->country === '' ? __('admin.tax.all_countries') : strtoupper($g->country) }}</td>
            <td>{{ $g->state === '' ? __('admin.tax.all') : $g->state }}</td>
            <td>{{ rtrim(rtrim(number_format((float) $g->default->tax_rate, 2), '0'), '.') }}%</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick='openEditTax({{ json_encode(['country'=>$g->country, 'state'=>$g->state, 'rates'=>$g->rules->map(fn($r)=>['name'=>$r->name,'rate'=>(float)$r->tax_rate,'is_default'=>(bool)$r->is_default])->values()]) }})'>{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.tax.destroy', ['country' => $g->country === '' ? '@global' : $g->country, 'state' => $g->state === '' ? null : $g->state]) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.tax.confirm_delete') }}')">
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

<div id="modal-tax" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-tax').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:680px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 id="modal-tax-title" style="margin:0;font-size:16px;">{{ __('admin.tax.add_rule') }}</h4>
            <button type="button" onclick="document.getElementById('modal-tax').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="tax-form" action="{{ route('admin.config.tax.store') }}">
            @csrf
            <input type="hidden" name="_method" id="tf-method" value="POST">
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:360px;">
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.tax.country') }}</label>
                        <input type="text" name="country" id="tf-country" required class="form-control" placeholder="PL" maxlength="2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.tax.state') }} <small style="color:#999;">{{ __('admin.tax.blank_all') }}</small></label>
                        <input type="text" name="state" id="tf-state" class="form-control" placeholder="TX">
                    </div>
                </div>
                <table class="data-table" style="margin:12px 0;">
                    <thead><tr><th>{{ __('admin.tax.tax_name') }}</th><th>{{ __('admin.tax.rate_col') }}</th><th style="text-align:center;">{{ __('admin.tax.default') }}</th><th></th></tr></thead>
                    <tbody id="tax-rate-rows"></tbody>
                </table>
                <button type="button" onclick="addRateRow()" class="btn btn-default btn-sm">+ {{ __('admin.tax.add_another') }}</button>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-tax').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
let rateIndex = 0;

function addRateRow(data) {
    const i = rateIndex++;
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="rates[${i}][name]" required class="form-control" style="min-width:180px;" placeholder="VAT 23%" value="${(data && data.name) || ''}"></td>
        <td><input type="number" name="rates[${i}][tax_rate]" step="0.01" required class="form-control" style="min-width:90px;" placeholder="23" value="${(data && data.rate) || ''}"></td>
        <td style="text-align:center;"><input type="radio" name="default_index" value="${i}" ${(data && data.is_default) ? 'checked' : ''}></td>
        <td><button type="button" onclick="this.closest('tr').remove()" class="btn btn-danger btn-xs">&times;</button></td>`;
    document.getElementById('tax-rate-rows').appendChild(row);
}

function openAddTax() {
    document.getElementById('modal-tax-title').textContent = '{{ __('admin.tax.add_rule') }}';
    document.getElementById('tax-form').action = '{{ route('admin.config.tax.store') }}';
    document.getElementById('tf-method').value = 'POST';
    document.getElementById('tf-country').value = '';
    document.getElementById('tf-state').value = '';
    document.getElementById('tax-rate-rows').innerHTML = '';
    rateIndex = 0;
    addRateRow();
    document.getElementById('modal-tax').style.display = 'flex';
}

function openEditTax(d) {
    document.getElementById('modal-tax-title').textContent = '{{ __('admin.tax.edit_rule') }}';
    const country = d.country === '' ? '@global' : d.country;
    let action = '/admin/config/tax/' + encodeURIComponent(country);
    if (d.state) {
        action += '/' + encodeURIComponent(d.state);
    }
    document.getElementById('tax-form').action = action;
    document.getElementById('tf-method').value = 'PUT';
    document.getElementById('tf-country').value = d.country || '';
    document.getElementById('tf-state').value = d.state || '';
    document.getElementById('tax-rate-rows').innerHTML = '';
    rateIndex = 0;
    d.rates.forEach(function (r) { addRateRow(r); });
    document.getElementById('modal-tax').style.display = 'flex';
}
</script>
@endsection
