{{--
    The address an invoice is issued to.

    Asked wherever an account is opened, because everything downstream needs
    it and none of it can be guessed: the tax rate is chosen from the
    country and state, the invoice PDF prints the address, e-invoicing sends
    it to the tax authority, and a domain registration is refused without a
    valid registrant address. Left to the profile page it is simply never
    filled in, and the first invoice goes out wrong.

    Expects: $countries (code => name); optionally $client for existing
    values and $gridClass for the two-column class the host page styles.
--}}
@php($addr = $client ?? null)
@php($grid = $gridClass ?? 'form-grid-2')
<div class="form-group">
    <label class="form-label" for="country">{{ __('common.form.country') }}<span class="req" style="color:#c43c35;">*</span></label>
    <select id="country" name="country" required class="form-control">
        <option value="">{{ __('common.form.select_country') }}</option>
        @foreach($countries as $code => $name)
            <option value="{{ $code }}" {{ old('country', $addr?->country) === $code ? 'selected' : '' }}>{{ $name }}</option>
        @endforeach
    </select>
    @error('country')<div class="text-danger text-sm">{{ $message }}</div>@enderror
</div>
<div class="form-group">
    <label class="form-label" for="address1">{{ __('common.form.street_address') }}<span class="req" style="color:#c43c35;">*</span></label>
    <input type="text" id="address1" name="address1" value="{{ old('address1', $addr?->address1) }}" required class="form-control" autocomplete="street-address">
    @error('address1')<div class="text-danger text-sm">{{ $message }}</div>@enderror
</div>
<div class="{{ $grid }}">
    <div class="form-group">
        <label class="form-label" for="city">{{ __('common.form.city') }}<span class="req" style="color:#c43c35;">*</span></label>
        <input type="text" id="city" name="city" value="{{ old('city', $addr?->city) }}" required class="form-control" autocomplete="address-level2">
        @error('city')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label class="form-label" for="postcode">{{ __('common.form.postcode') }}<span class="req" style="color:#c43c35;">*</span></label>
        <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $addr?->postcode) }}" required class="form-control" autocomplete="postal-code">
        @error('postcode')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
</div>
@if(\App\Support\BillingIdentity::turkish())
{{-- Where the seller is bound by the Turkish invoicing rules the buyer has to
     be typed: a company by trade title, tax office and tax number, a private
     person by national ID. Elsewhere the tax number below is enough. --}}
<div class="form-group" data-billing-identity>
    <label class="form-label">{{ __('client.form.client_type') }}<span class="req" style="color:#c43c35;">*</span></label>
    <div style="display:flex;gap:18px;margin:4px 0 8px;">
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:14px;">
            <input type="radio" name="client_type" value="individual" @checked(old('client_type', $addr?->client_type ?: 'individual') === 'individual') required>
            {{ __('client.form.client_type_individual') }}
        </label>
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:14px;">
            <input type="radio" name="client_type" value="company" @checked(old('client_type', $addr?->client_type) === 'company')>
            {{ __('client.form.client_type_company') }}
        </label>
    </div>
    @error('client_type')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    <div data-identity-for="individual" class="form-group">
        <label class="form-label" for="national_id">{{ __('client.form.national_id') }}<span class="req" style="color:#c43c35;">*</span></label>
        <input type="text" id="national_id" name="national_id" value="{{ old('national_id', $addr?->national_id) }}" inputmode="numeric" maxlength="20" class="form-control">
        @error('national_id')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
    <div data-identity-for="company">
        <div class="form-group">
            <label class="form-label" for="company_name">{{ __('client.form.company_title') }}<span class="req" style="color:#c43c35;">*</span></label>
            <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $addr?->company_name) }}" class="form-control">
            @error('company_name')<div class="text-danger text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="tax_office">{{ __('client.form.tax_office') }}<span class="req" style="color:#c43c35;">*</span></label>
            <input type="text" id="tax_office" name="tax_office" value="{{ old('tax_office', $addr?->tax_office) }}" class="form-control">
            @error('tax_office')<div class="text-danger text-sm">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-billing-identity]');
    if (!root) { return; }
    var radios = root.querySelectorAll('input[name="client_type"]');
    function apply() {
        var picked = root.querySelector('input[name="client_type"]:checked');
        var type = picked ? picked.value : 'individual';
        root.querySelectorAll('[data-identity-for]').forEach(function (block) {
            block.hidden = block.getAttribute('data-identity-for') !== type;
        });
    }
    Array.prototype.forEach.call(radios, function (r) { r.addEventListener('change', apply); });
    apply();
})();
</script>
@endif
<div class="{{ $grid }}">
    <div class="form-group">
        <label class="form-label" for="state">{{ __('common.form.state') }}</label>
        <input type="text" id="state" name="state" value="{{ old('state', $addr?->state) }}" class="form-control" autocomplete="address-level1">
        @error('state')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label class="form-label" for="tax_id">{{ __('common.form.tax_id') }}</label>
        <input type="text" id="tax_id" name="tax_id" value="{{ old('tax_id', $addr?->tax_id) }}" class="form-control">
        <small class="text-muted">{{ __('common.form.tax_id_hint') }}</small>
        @error('tax_id')<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
</div>
