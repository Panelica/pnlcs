@extends('admin.layouts.app')
@section('title', __('admin.products.create_product'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.products.create_product') }}</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>

@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.products.store') }}">
    @csrf
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.products.product_details') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">{{ __('admin.products.product_name') }} <span style="color:#d9534f;">*</span></label><input type="text" name="name" value="{{ old('name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.product_group') }} <span style="color:#d9534f;">*</span></label><select name="group_id" required class="form-control">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.product_type') }} <span style="color:#d9534f;">*</span></label><select name="type" class="form-control"><option value="hosting">{{ __('admin.products.type_hosting') }}</option><option value="reseller">{{ __('admin.products.type_reseller') }}</option><option value="vps">{{ __('admin.products.type_vps') }}</option><option value="ssl">{{ __('admin.products.type_ssl') }}</option><option value="other">{{ __('admin.products.type_other') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.payment_type') }} <span style="color:#d9534f;">*</span></label><select name="pay_type" class="form-control"><option value="recurring">{{ __('admin.products.pay_recurring') }}</option><option value="onetime">{{ __('admin.products.pay_onetime') }}</option><option value="free">{{ __('admin.products.pay_free') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.auto_setup') }}</label>
                    <select name="auto_setup" class="form-control">
                        <option value="payment">{{ __('admin.products.auto_setup_payment') }}</option>
                        <option value="order">{{ __('admin.products.auto_setup_order') }}</option>
                        <option value="manual">{{ __('admin.products.auto_setup_manual') }}</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.server_module') }}</label>
                    <select name="server_type" class="form-control">
                        <option value="">{{ __('admin.products.server_module_none') }}</option>
                        @foreach($serverModules as $key => $label)
                        <option value="{{ $key }}" @selected(old('server_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div style="color:#777;font-size:12px;margin-top:4px;">{{ __('admin.products.server_module_hint') }}</div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.server_group') }}</label>
                    <select name="server_group_id" class="form-control">
                        <option value="">{{ __('admin.products.server_group_any') }}</option>
                        @foreach($serverGroups as $sg)
                        <option value="{{ $sg->id }}" @selected((string) old('server_group_id') === (string) $sg->id)>{{ $sg->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.products.package') }}</label>
                    <select name="package_name" id="package-select" class="form-control">
                        <option value="">{{ __('admin.products.package_default') }}</option>
                        @foreach(($packageList['packages'] ?? []) as $pkg)
                        <option value="{{ $pkg['id'] }}" @selected(($selectedPackage ?? '') === $pkg['id'])>{{ $pkg['name'] }}</option>
                        @endforeach
                    </select>
                    <div id="package-note" style="color:#777;font-size:12px;margin-top:4px;">
                        {{ $packageList['error'] ?? __('admin.products.package_hint') }}
                    </div>
                </div>                <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.products.pricing') }}</strong></div>
        <div class="card-body">
            @foreach($currencies as $currency)
            <div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:15px;margin-bottom:12px;">
                <p style="font-weight:600;font-size:13px;margin:0 0 10px;">{{ $currency->code }} ({{ $currency->prefix }})</p>
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;">
                    @foreach(['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cycle)
                    <div>
                        <label class="form-label" style="text-transform:capitalize;">{{ $cycle }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="-1" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
                <p style="font-size:11px;color:#999;margin:6px 0 0;">{{ __('admin.products.disable_cycle_hint') }}</p>
            </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">{{ __('admin.products.create_product') }}</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var moduleSelect = document.querySelector('select[name="server_type"]');
    var packageSelect = document.getElementById('package-select');
    var note = document.getElementById('package-note');
    if (! moduleSelect || ! packageSelect) { return; }

    moduleSelect.addEventListener('change', function () {
        var chosen = packageSelect.value;
        packageSelect.innerHTML = '<option value="">@lang('admin.products.package_loading')</option>';

        fetch('{{ route('admin.products.packages') }}?module=' + encodeURIComponent(moduleSelect.value), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            packageSelect.innerHTML = '<option value="">@lang('admin.products.package_default')</option>';
            (data.packages || []).forEach(function (p) {
                var o = document.createElement('option');
                o.value = p.id;
                o.textContent = p.name;
                if (p.id === chosen) { o.selected = true; }
                packageSelect.appendChild(o);
            });
            note.textContent = data.error || '@lang('admin.products.package_hint')';
        })
        .catch(function () {
            packageSelect.innerHTML = '<option value="">@lang('admin.products.package_default')</option>';
            note.textContent = '@lang('admin.products.package_list_unreachable')';
        });
    });
})();
</script>
@endpush
