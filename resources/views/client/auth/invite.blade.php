<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('client.invite.title') }} - {{ company_name() }}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:var(--card,#fff);border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.08);width:440px;max-width:95%;padding:40px}h1{font-size:22px;font-weight:700;color:#1a4d80;margin-bottom:12px}p{font-size:14px;color:var(--text,#334155);margin-bottom:20px;line-height:1.5}.fg{margin-bottom:16px}.fl{display:block;font-size:13px;font-weight:600;color:var(--text,#334155);margin-bottom:6px}.fc{width:100%;padding:10px 14px;border:1.5px solid var(--border,#cbd5e1);border-radius:8px;font-size:14px;outline:none}.fc:focus{border-color:#1a4d80}.btn{display:block;text-align:center;text-decoration:none;width:100%;padding:12px;background:#1a4d80;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}.btn:hover{background:#143d66}.ae{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px}</style></head>
<body><div class="card">
<h1>{{ __('client.invite.title') }}</h1>
<p>{{ __('client.invite.intro', ['company' => company_name(), 'account' => trim(($account->company_name ?: $account->first_name.' '.$account->last_name)), 'email' => $invite->email]) }}</p>
@if($errors->any())<div class="ae">@foreach($errors->all() as $e){{ $e }} @endforeach</div>@endif

@if($hasLogin)
    @if($signedInAs && $signedInAs->email === $invite->email)
        <form method="POST" action="{{ route('client.invite.accept', $token) }}">@csrf
            <button type="submit" class="btn">{{ __('client.invite.accept') }}</button>
        </form>
    @else
        <p>{{ __('client.invite.sign_in_first', ['email' => $invite->email]) }}</p>
        <a class="btn" href="{{ route('client.login') }}">{{ __('client.invite.sign_in') }}</a>
    @endif
@else
    <form method="POST" action="{{ route('client.invite.accept', $token) }}">@csrf
        <div class="fg"><label class="fl">{{ __('common.form.email') }}</label><input type="email" class="fc" value="{{ $invite->email }}" disabled></div>
        <div class="fg"><label class="fl">{{ __('common.form.first_name') }}</label><input type="text" name="first_name" class="fc" value="{{ old('first_name') }}" required maxlength="255"></div>
        <div class="fg"><label class="fl">{{ __('common.form.last_name') }}</label><input type="text" name="last_name" class="fc" value="{{ old('last_name') }}" required maxlength="255"></div>
        <div class="fg"><label class="fl">{{ __('common.form.new_password') }}</label><input type="password" name="password" class="fc" minlength="8" required></div>
        <div class="fg"><label class="fl">{{ __('common.form.confirm_password') }}</label><input type="password" name="password_confirmation" class="fc" required></div>
        <button type="submit" class="btn">{{ __('client.invite.create_and_accept') }}</button>
    </form>
@endif
</div></body></html>
