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

@endsection
