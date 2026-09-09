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
