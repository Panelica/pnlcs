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

<form method="POST" action="{{ route("admin.my-account.update") }}">
    @csrf

    <div class="card" style="max-width:640px;">
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
            </div>
        </div>
    </div>

    <div class="card" style="max-width:640px;margin-top:16px;">
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

    <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        <a href="{{ route("admin.dashboard") }}" class="btn btn-default" style="margin-left:8px;">{{ __('common.actions.cancel') }}</a>
    </div>
</form>

<div class="card" style="margin-top:16px;">
    <div class="card-header">{{ __('admin.settings.two_factor') }}</div>
    <div class="card-body">
        <p style="color:#777;font-size:13px;margin-top:0;">{{ __('admin.settings.two_factor_desc') }}</p>
        @if($admin->second_factor_type)
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:13px;color:#3c763d;font-weight:500;">&#10003; {{ __('admin.settings.two_factor_on') }}</span>
                <form method="POST" action="{{ route('admin.2fa.disable') }}" style="display:flex;gap:8px;align-items:flex-start;margin:0;">
                    @csrf
                    <div>
                        <input type="password" name="password" class="form-control" placeholder="{{ __('admin.settings.confirm_with_password') }}" autocomplete="current-password" style="min-width:220px;">
                        @error('password')<div style="color:#c43c35;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.settings.two_factor_turn_off') }}</button>
                </form>
            </div>
        @else
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:13px;color:#777;">{{ __('admin.settings.two_factor_off') }}</span>
                <a href="{{ route('admin.2fa.enable') }}" class="btn btn-primary btn-sm">{{ __('admin.settings.two_factor_turn_on') }}</a>
            </div>
        @endif
    </div>
</div>

@endsection
