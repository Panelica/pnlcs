<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('client.auth.register_title') }} - {{ company_name() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 13px; background: #f6f6f6; color: #333; margin: 0; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; }
        .register-box { width: 100%; max-width: 480px; }
        .login-logo { text-align: center; margin-bottom: 24px; }
        .login-logo h1 { font-size: 26px; font-weight: 700; color: #1a4d80; margin: 0 0 6px; }
        .login-logo p { font-size: 13px; color: #777; margin: 0; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .card-body { padding: 28px; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 4px; }
        .form-control { display: block; width: 100%; padding: 7px 12px; font-size: 13px; color: #555; background: #fff; border: 1px solid #ccc; border-radius: 4px; transition: border-color 0.15s; }
        .form-control:focus { border-color: #66afe9; outline: 0; box-shadow: 0 0 6px rgba(102,175,233,.5); }
        select.form-control { height: 34px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 7px 16px; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid transparent; text-decoration: none; width: 100%; }
        .btn-primary { background: #337ab7; color: #fff; border-color: #2e6da4; }
        .btn-primary:hover { background: #286090; }
        .alert { padding: 9px 12px; border-radius: 4px; font-size: 13px; margin-bottom: 14px; background: #f2dede; border: 1px solid #ebccd1; color: #a94442; }
        .alert li { margin: 2px 0; }
        .login-link { text-align: center; margin-top: 16px; font-size: 13px; color: #777; }
        .login-link a { color: #337ab7; font-weight: 500; text-decoration: none; }
        .field-note { font-size: 12px; color: #999; margin-top: 3px; }
    </style>
</head>
<body>
<div class="register-box">
    <div class="login-logo">
        <h1>{{ company_name() }}</h1>
        <p>{{ __('client.auth.create_your_account') }}</p>
    </div>
    <div class="card">
        <div class="card-body">
            @if($errors->any())
                <div class="alert">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('client.register.submit') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">{{ __('common.form.first_name') }}<span style="color:#c43c35;">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">{{ __('common.form.last_name') }}<span style="color:#c43c35;">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">{{ __('common.form.email_address') }}<span style="color:#c43c35;">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">{{ __('common.form.password') }}<span style="color:#c43c35;">*</span></label>
                    <input type="password" id="password" name="password" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">{{ __('common.form.confirm_password') }}<span style="color:#c43c35;">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="phone_number">{{ __('client.form.phone') }} <span style="color:#999; font-weight:400;">({{ __('client.form.optional') }})</span></label>
                        <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="company_name">{{ __('client.form.company') }} <span style="color:#999; font-weight:400;">({{ __('client.form.optional') }})</span></label>
                        <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" class="form-control">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" name="tos" value="1" {{ old('tos') ? 'checked' : '' }} style="margin-top:3px;" required>
                        @php
                            // A link to "#" reads as a document the customer can
                            // open and is not one. Name the thing either way;
                            // link it only when there is somewhere to go.
                            $legalLink = function (string $url, string $label) {
                                $url = trim($url);

                                return $url === ''
                                    ? e($label)
                                    : '<a href="'.e($url).'" target="_blank" rel="noopener" style="color:#337ab7;text-decoration:underline;">'.e($label).'</a>';
                            };
                        @endphp
                        <span>{{ __('client.auth.i_agree_to') }} {!! $legalLink((string) \App\Models\Setting::get('TOSUrl', ''), __('client.auth.terms_of_service')) !!} {{ __('client.auth.and') }} {!! $legalLink((string) \App\Models\Setting::get('PrivacyUrl', ''), __('client.auth.privacy_policy')) !!}.</span>
                    </label>
                    @error('tos') <span style="color:#c43c35;font-size:12px;">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:4px;">{{ __('client.auth.create_account') }}</button>
            </form>
        </div>
    </div>
    <div class="login-link">
        {{ __('client.auth.already_have_account') }} <a href="{{ route('client.login') }}">{{ __('client.auth.sign_in') }}</a>
    </div>
</div>
</body>
</html>
