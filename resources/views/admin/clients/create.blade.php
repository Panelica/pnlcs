@extends('admin.layouts.app')
@section('title', __('admin.clients.add_client'))
@section('content')
<div class="page-header">
    <h1>{{ __('admin.clients.add_new_client') }}</h1>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-default btn-sm">&larr; {{ __('common.actions.back') }}</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.first_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="first_name" value="{{ old('first_name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.last_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="last_name" value="{{ old('last_name') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.email') }}<span style="color:#d9534f;">*</span></label><input type="email" name="email" value="{{ old('email') }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.password') }}<small style="color:#999;font-weight:400;"> ({{ __('admin.clients.password_optional') }})</small></label><input type="password" name="password" class="form-control" autocomplete="new-password"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.password_confirm') }}</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.company') }}</label><input type="text" name="company_name" value="{{ old('company_name') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.tax_id') }}</label><input type="text" name="tax_id" value="{{ old('tax_id') }}" maxlength="20" class="form-control"></div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('common.form.address') }}</label><input type="text" name="address1" value="{{ old('address1') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.city') }}</label><input type="text" name="city" value="{{ old('city') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.state') }}</label><input type="text" name="state" value="{{ old('state') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.postcode') }}</label><input type="text" name="postcode" value="{{ old('postcode') }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.country') }}</label>
                    <select name="country" id="country" class="form-control">
                        @foreach(\App\Support\Countries::all() as $code => $name)
                        <option value="{{ $code }}" {{ old('country', 'US') == $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.phone') }}</label>
                    <div style="display:flex;gap:6px;">
                        <select name="phone_prefix" id="phone_prefix" class="form-control" style="width:90px !important;flex-shrink:0;">
                            @foreach(\App\Support\Countries::PHONE_PREFIXES as $code => $prefix)
                            <option value="{{ $prefix }}" {{ old('phone_prefix') == $prefix ? 'selected' : '' }}>{{ $code }} {{ $prefix }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="phone_number" value="{{ old('phone_number') }}" class="form-control" style="flex:1;min-width:0;">
                    </div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.status') }}</label>
                    <select name="status" class="form-control"><option value="active">{{ __('common.status.active') }}</option><option value="inactive">{{ __('common.status.inactive') }}</option><option value="closed">{{ __('common.status.closed') }}</option></select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.group') }}</label>
                    <select name="group_id" class="form-control"><option value="">{{ __('common.none') }}</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.currency') }}</label>
                    <select name="currency_id" class="form-control">@foreach($currencies as $c)<option value="{{ $c->id }}" {{ $c->is_default ? 'selected' : '' }}>{{ $c->code }} ({{ $c->prefix }})</option>@endforeach</select>
                </div>
            </div>
            @if($customFields->isNotEmpty())
            <hr style="margin:18px 0;border:none;border-top:1px solid #e5e5e5;">
            <h4 style="margin:0 0 12px;font-size:14px;font-weight:600;">{{ __('admin.clients.custom_fields') }}</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                @foreach($customFields as $field)
                <div class="form-group" @if($field->field_type === 'textarea') style="grid-column:span 2;" @endif>
                    <label class="form-label">{{ $field->field_name }}@if($field->required)<span style="color:#d9534f;">*</span>@endif</label>
                    @if($field->field_type === 'textarea')
                        <textarea name="custom_fields[{{ $field->id }}]" rows="3" class="form-control" @if($field->required) required @endif>{{ old("custom_fields.{$field->id}") }}</textarea>
                    @elseif($field->field_type === 'select')
                        <select name="custom_fields[{{ $field->id }}]" class="form-control" @if($field->required) required @endif>
                            <option value="">{{ __('common.none') }}</option>
                            @foreach($field->options() as $opt)
                            <option value="{{ $opt }}" @if(old("custom_fields.{$field->id}") === $opt) selected @endif>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($field->field_type === 'checkbox')
                        <div style="padding-top:6px;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                                <input type="checkbox" name="custom_fields[{{ $field->id }}]" value="1" @if(old("custom_fields.{$field->id}")) checked @endif> {{ __('admin.custom_fields.checkbox_yes') }}
                            </label>
                        </div>
                    @elseif($field->field_type === 'number')
                        <input type="number" name="custom_fields[{{ $field->id }}]" value="{{ old("custom_fields.{$field->id}") }}" class="form-control" @if($field->required) required @endif>
                    @elseif($field->field_type === 'date')
                        <input type="date" name="custom_fields[{{ $field->id }}]" value="{{ old("custom_fields.{$field->id}") }}" class="form-control" @if($field->required) required @endif>
                    @else
                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ old("custom_fields.{$field->id}") }}" class="form-control" @if($field->regex) pattern="{{ $field->regex }}" @endif @if($field->required) required @endif>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
            <div style="margin-top:10px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">{{ __('admin.clients.create_client') }}</button>
                <a href="{{ route('admin.clients.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var map = {!! json_encode(\App\Support\Countries::PHONE_PREFIXES) !!};
    var country = document.getElementById('country');
    var prefix = document.getElementById('phone_prefix');
    function sync() {
        var code = (country.value || '').toUpperCase();
        if (map[code] && prefix) prefix.value = map[code];
    }
    if (country) country.addEventListener('change', sync);
    if (country) sync();
})();
</script>
@endsection
