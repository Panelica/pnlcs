@extends('admin.layouts.app')
@section('title', __('admin.clients.edit_client') . ': ' . $client->full_name)
@section('content')
<div class="page-header">
    <h1>{{ __('admin.clients.edit_client') }}: {{ $client->full_name }}</h1>
    <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-default btn-sm">&larr; Back</a>
</div>
@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    @foreach($errors->all() as $e)<div>&bull; {{ $e }}</div>@endforeach
</div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.clients.update', $client) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.first_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.last_name') }}<span style="color:#d9534f;">*</span></label><input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.email') }}<span style="color:#d9534f;">*</span></label><input type="email" name="email" value="{{ old('email', $client->email) }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.billing_email') }}</label><input type="email" name="billing_email" value="{{ old('billing_email', $client->billing_email) }}" class="form-control"><div style="color:var(--muted);font-size:12px;margin-top:4px;">{{ __('admin.clients.billing_email_hint') }}</div></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.company') }}</label><input type="text" name="company_name" value="{{ old('company_name', $client->company_name) }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.tax_id') }}</label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" name="tax_id" value="{{ old('tax_id', $client->tax_id) }}" maxlength="20" class="form-control" style="flex:1;min-width:0;">
                        <x-company-lookup />
                    </div>
                </div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('common.form.address') }}</label><input type="text" name="address1" value="{{ old('address1', $client->address1) }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.city') }}</label><input type="text" name="city" value="{{ old('city', $client->city) }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.state') }}</label><input type="text" name="state" value="{{ old('state', $client->state) }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.postcode') }}</label><input type="text" name="postcode" value="{{ old('postcode', $client->postcode) }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.country') }}</label>
                    <select name="country" id="country" class="form-control">
                        @foreach(\App\Support\Countries::all() as $code => $name)
                        <option value="{{ $code }}" {{ old('country', $client->country) == $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.phone') }}</label>
                    <div style="display:flex;gap:6px;">
                        <select name="phone_prefix" id="phone_prefix" class="form-control" style="width:90px !important;flex-shrink:0;">
                            @foreach(\App\Support\Countries::PHONE_PREFIXES as $code => $prefix)
                            <option value="{{ $prefix }}" {{ old('phone_prefix', $client->phone_prefix ?? \App\Support\Countries::phonePrefix($client->country)) == $prefix ? 'selected' : '' }}>{{ $code }} {{ $prefix }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="phone_number" value="{{ old('phone_number', $client->phone_number) }}" class="form-control" style="flex:1;min-width:0;">
                    </div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.status') }}</label>
                    <select name="status" class="form-control">
                        <option value="active" {{ $client->status->value == 'active' ? 'selected' : '' }}>{{ __('common.status.active') }}</option>
                        <option value="inactive" {{ $client->status->value == 'inactive' ? 'selected' : '' }}>{{ __('common.status.inactive') }}</option>
                        <option value="closed" {{ $client->status->value == 'closed' ? 'selected' : '' }}>{{ __('common.status.closed') }}</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.default_payment_method') }}<span style="color:#d9534f;">*</span></label>
                    <select name="default_payment_method" class="form-control">
                        @foreach($paymentMethods as $pm)
                        <option value="{{ $pm }}" {{ old('default_payment_method', $client->default_payment_method) == $pm ? 'selected' : '' }}>{{ \payment_method_label($pm) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <hr style="margin:18px 0;border:none;border-top:1px solid #e5e5e5;">
            <h4 style="margin:0 0 12px;font-size:14px;font-weight:600;">{{ __('common.form.password') }}</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('common.form.new_password') }}<small style="color:#999;font-weight:400;"> ({{ __('admin.clients.password_optional_edit') }})</small></label>
                    <div style="display:flex;gap:6px;">
                        <input type="password" name="password" id="pw-input" class="form-control" autocomplete="new-password" oninput="checkStrength(this.value)">
                        <button type="button" onclick="generatePassword()" class="btn btn-default" style="white-space:nowrap;flex-shrink:0;" title="{{ __('admin.clients.generate_password') }}">&#x1F512; {{ __('admin.clients.generate_password') }}</button>
                        <button type="button" onclick="copyPw('pw-input', this)" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.actions.copy') }}">&#128203;</button>
                        <button type="button" onclick="togglePw('pw-input')" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.actions.toggle') }}">&#128065;</button>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">{{ __('common.form.password_confirm') }}</label><input type="password" name="password_confirmation" id="pw-confirm" class="form-control" autocomplete="new-password"></div>
                <div class="form-group" style="grid-column:span 2;">
                    <div style="height:5px;border-radius:999px;background:#e5e5e5;overflow:hidden;margin-top:2px;"><div id="pwBar" style="height:100%;border-radius:999px;transition:width 0.3s,background 0.3s;width:0;background:#ef4444;"></div></div>
                    <div id="pwHint" style="font-size:12px;color:#999;margin-top:4px;"></div>
                </div>
            </div>
            @if($customFields->isNotEmpty())
            <hr style="margin:18px 0;border:none;border-top:1px solid #e5e5e5;">
            <h4 style="margin:0 0 12px;font-size:14px;font-weight:600;">{{ __('admin.clients.custom_fields') }}</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 15px;">
                @foreach($customFields as $field)
                @php($value = old("custom_fields.{$field->id}", $field->values->first()?->value))
                <div class="form-group" @if($field->field_type === 'textarea') style="grid-column:span 2;" @endif>
                    <label class="form-label">{{ $field->field_name }}@if($field->required)<span style="color:#d9534f;">*</span>@endif</label>
                    @if($field->field_type === 'textarea')
                        <textarea name="custom_fields[{{ $field->id }}]" rows="3" class="form-control" @if($field->required) required @endif>{{ $value }}</textarea>
                    @elseif($field->field_type === 'select')
                        <select name="custom_fields[{{ $field->id }}]" class="form-control" @if($field->required) required @endif>
                            <option value="">{{ __('common.none') }}</option>
                            @foreach($field->options() as $opt)
                            <option value="{{ $opt }}" @if($value === $opt) selected @endif>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($field->field_type === 'checkbox')
                        <div style="padding-top:6px;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                                <input type="checkbox" name="custom_fields[{{ $field->id }}]" value="1" @if($value) checked @endif> {{ __('admin.custom_fields.checkbox_yes') }}
                            </label>
                        </div>
                    @elseif($field->field_type === 'number')
                        <input type="number" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->required) required @endif>
                    @elseif($field->field_type === 'date')
                        <input type="date" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->required) required @endif>
                    @else
                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->regex) pattern="{{ $field->regex }}" @endif @if($field->required) required @endif>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
            <div style="margin-top:10px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">{{ __('admin.clients.update_client') }}</button>
                <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
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
})();
</script>
<script>
function generatePassword() {
    var upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    var lower = "abcdefghijkmnopqrstuvwxyz";
    var digits = "23456789";
    var symbols = "!@#$%^&*_-+=?";
    var all = upper + lower + digits + symbols;
    var out = [];
    out.push(upper[Math.floor(Math.random() * upper.length)]);
    out.push(lower[Math.floor(Math.random() * lower.length)]);
    out.push(digits[Math.floor(Math.random() * digits.length)]);
    out.push(symbols[Math.floor(Math.random() * symbols.length)]);
    for (var i = 0; i < 12; i++) {
        out.push(all[Math.floor(Math.random() * all.length)]);
    }
    out.sort(function () { return Math.random() - 0.5; });
    var pw = out.join("");
    var input = document.getElementById("pw-input"), confirmInput = document.getElementById("pw-confirm");
    if (input) { input.type = "text"; input.value = pw; input.focus(); }
    if (confirmInput) { confirmInput.type = "text"; confirmInput.value = pw; }
    checkStrength(pw);
}
function togglePw(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === "password" ? "text" : "password";
}
function copyPw(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var prev = el.type;
    el.type = "text";
    el.select();
    try { document.execCommand("copy"); } catch (e) {}
    el.type = prev;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).catch(function () {});
    }
    if (btn) {
        var orig = btn.innerHTML;
        btn.innerHTML = "&#10003;";
        setTimeout(function () { btn.innerHTML = orig; }, 1200);
    }
}
function checkStrength(v) {
    var bar = document.getElementById("pwBar"), hint = document.getElementById("pwHint");
    if (!bar || !hint) return;
    var score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var levels = [
        {w:"0%",c:"#ef4444",t:"{{ __('client.password.too_short') }}"},
        {w:"20%",c:"#ef4444",t:"{{ __('client.password.very_weak') }}"},
        {w:"40%",c:"#f59e0b",t:"{{ __('client.password.weak') }}"},
        {w:"60%",c:"#f59e0b",t:"{{ __('client.password.fair') }}"},
        {w:"80%",c:"#06d6a0",t:"{{ __('client.password.strong') }}"},
        {w:"100%",c:"#10b981",t:"{{ __('client.password.very_strong') }}"}
    ];
    var l = levels[Math.min(score, 5)];
    bar.style.width = l.w; bar.style.background = l.c;
    hint.textContent = l.t; hint.style.color = l.c;
}
</script>
@endsection
