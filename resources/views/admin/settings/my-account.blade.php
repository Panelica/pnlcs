@extends("admin.layouts.app")
@section("title", __("admin.settings.my_account"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.settings.my_account_title') }}</h1>
</div>

@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="account-layout">
    <div class="account-primary">
        <form method="POST" action="{{ route("admin.my-account.update") }}">
            @csrf

            <div class="card">
                <div class="card-header">{{ __('admin.settings.profile_information') }}</div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label class="form-label">{{ __('common.form.first_name') }}<span style="color:#c43c35;">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="{{ old("first_name", $admin->first_name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.form.last_name') }}<span style="color:#c43c35;">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="{{ old("last_name", $admin->last_name) }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.email_address') }}<span style="color:#c43c35;">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old("email", $admin->email) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.ticket_signature') }} <small style="color:#999;">{{ __('admin.settings.ticket_signature_hint') }}</small></label>
                        <textarea name="signature" class="form-control" rows="4" style="resize:vertical;">{{ old("signature", $admin->signature) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.language') }}<span style="color:#c43c35;">*</span></label>
                        <select name="language" class="form-control" required>
                            @foreach($languages as $language)
                                <option value="{{ $language->code }}" {{ old('language', $admin->language) === $language->code ? 'selected' : '' }}>
                                    {{ $language->native_name }}
                                </option>
                            @endforeach
                        </select>
                        @php
                            // Only active languages are offered here. The menu path is the
                            // sidebar's own labels, a link for an admin who may open it.
                            $languagesMenu = __('admin.nav.setup').' → '.__('admin.nav.languages');
                            [$hintBefore, $hintAfter] = array_pad(explode(':menu', __('admin.settings.language_active_only'), 2), 2, '');
                        @endphp
                        <small class="language-active-hint" style="color:#999;display:block;margin-top:4px;">{{ $hintBefore }}@if($admin->role?->hasPermission('manage_settings'))<a href="{{ route('admin.config.languages.index') }}">{{ $languagesMenu }}</a>@else{{ $languagesMenu }}@endif{{ $hintAfter }}</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('admin.settings.change_password') }} <small style="font-weight:400;color:#999;">{{ __('admin.settings.change_password_hint') }}</small></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.new_password') }}</label>
                        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.confirm_new_password') }}</label>
                        <input type="password" name="new_password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="account-actions">
                <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
                <a href="{{ route("admin.dashboard") }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>

    <aside class="account-side">
        <div class="card">
            <div class="card-header">{{ __('admin.settings.two_factor') }}</div>
            <div class="card-body">
                <p class="account-muted">{{ __('admin.settings.two_factor_desc') }}</p>
                @if($admin->second_factor_type)
                    <div class="account-status">
                        <span class="account-status-on">&#10003; {{ __('admin.settings.two_factor_on') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.2fa.disable') }}" class="account-disable-form">
                        @csrf
                        <input type="password" name="password" class="form-control" placeholder="{{ __('admin.settings.confirm_with_password') }}" autocomplete="current-password">
                        @error('password')<div class="account-error">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.settings.two_factor_turn_off') }}</button>
                    </form>
                @else
                    <div class="account-status">
                        <span class="account-muted">{{ __('admin.settings.two_factor_off') }}</span>
                        <a href="{{ route('admin.2fa.enable') }}" class="btn btn-primary btn-sm">{{ __('admin.settings.two_factor_turn_on') }}</a>
                    </div>
                @endif
            </div>
        </div>

        @if($admin->second_factor_type)
        <div class="card">
            <div class="card-header">{{ __('admin.settings.trusted_devices') }}</div>
            <div class="card-body">
                <p class="account-muted">{{ __('admin.settings.trusted_devices_desc') }}</p>

                @if($trustedDevices->isEmpty())
                    <p class="account-empty">{{ __('admin.settings.trusted_devices_empty') }}</p>
                @else
                    <div class="trusted-devices">
                        @foreach($trustedDevices as $device)
                            <div class="trusted-device">
                                <div class="trusted-device-main">
                                    <div class="trusted-device-title">
                                        {{ $device->ip_address ?: __('admin.settings.trusted_device_unknown_ip') }}
                                        @if($currentDevice && $currentDevice->id === $device->id)
                                            <span class="trusted-device-badge">{{ __('admin.settings.trusted_device_current') }}</span>
                                        @endif
                                    </div>
                                    @if($device->user_agent)
                                        <div class="trusted-device-meta">{{ \Illuminate\Support\Str::limit($device->user_agent, 72) }}</div>
                                    @endif
                                    <div class="trusted-device-meta">
                                        {{ __('admin.settings.trusted_device_last_used') }}:
                                        {{ $device->last_used_at ? $device->last_used_at->diffForHumans() : '—' }}
                                        &middot;
                                        {{ __('admin.settings.trusted_device_expires') }}:
                                        {{ $device->expires_at->translatedFormat('Y-m-d H:i') }}
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.my-account.trusted-devices.destroy', $device) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-default btn-sm">{{ __('admin.settings.trusted_device_remove') }}</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="account-hint">{{ __('admin.settings.trusted_devices_hint') }}</p>
            </div>
        </div>
        @endif
    </aside>
</div>

@endsection
