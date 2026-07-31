@extends("client.layouts.app")
@section("title", __("client.edit_profile"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.profile.edit_profile') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.profile.edit_subtitle') }}</p>
    </div>
</div>

@if(($accounts ?? collect())->count() > 1)
<div class="pn-card" style="margin-bottom:16px;">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.account.accounts') }}</span></div>
    <div class="pn-card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">{{ __('client.account.accounts_hint') }}</p>
        @foreach($accounts as $account)
            <form method="POST" action="{{ route('client.account.switch', $account) }}" style="display:inline-block;margin:0 8px 8px 0;">
                @csrf
                <button type="submit" class="btn {{ $client && $client->id === $account->id ? 'btn-primary' : 'btn-default' }} btn-sm" @disabled($client && $client->id === $account->id)>
                    {{ trim($account->first_name.' '.$account->last_name) ?: $account->email }}
                </button>
            </form>
        @endforeach
    </div>
</div>
@endif
<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.profile.personal_info') }}</span></div>
    <div class="pn-card-body">
        @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route("client.account.update") }}">
            @csrf
            @method("PUT")
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="first_name">{{ __('common.form.first_name') }}<span class="req">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old("first_name", $user->first_name) }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">{{ __('common.form.last_name') }}<span class="req">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old("last_name", $user->last_name) }}" required class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.form.email_address') }}<span class="req">*</span></label>
                <input type="email" id="email" name="email" value="{{ old("email", $user->email) }}" required class="form-control">

            </div>
            <div class="form-group">
                <label class="form-label" for="current_password">{{ __('client.account.current_password') }}</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="form-control">
                <div style="color:#777;font-size:12px;margin-top:4px;">{{ __('client.account.email_change_needs_password') }}</div>
                @error('current_password')<div style="color:#c00;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="company_name">{{ __('common.form.company_name') }}</label>
                <input type="text" id="company_name" name="company_name" value="{{ old("company_name", $user->company_name) }}" class="form-control">
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="phone_number">{{ __('common.form.phone_number') }}</label>
                    <input type="text" id="phone_number" name="phone_number" value="{{ old("phone_number", $user->phone_number) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">{{ __('common.form.country') }}</label>
                    <select id="country" name="country" class="form-control">
                        <option value="">{{ __('common.form.select_country') }}</option>
                        @if(isset($countries))
                            @foreach($countries as $code => $name)
                            <option value="{{ $code }}" {{ old("country", $user->country) == $code ? "selected" : "" }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="address1">{{ __('common.form.street_address') }}</label>
                <input type="text" id="address1" name="address1" value="{{ old("address1", $user->address1) }}" class="form-control" placeholder="{{ __('common.form.street_address_placeholder') }}">
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">{{ __('common.form.city') }}</label>
                    <input type="text" id="city" name="city" value="{{ old("city", $user->city) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="postcode">{{ __('common.form.postcode') }}</label>
                    <input type="text" id="postcode" name="postcode" value="{{ old("postcode", $user->postcode) }}" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        </form>
    </div>
</div>

@endsection

