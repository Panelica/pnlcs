@extends("client.layouts.app")
@section("title", __("client.email_verify.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.email_verify.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.email_verify.subtitle') }}</p>
    </div>
</div>

<div class="pn-card" style="max-width:640px;">
    <div class="pn-card-body">
        {{-- The address is spelled out: a typo in it is the single most common
             reason the mail never arrives, and the customer cannot spot one
             they were never shown. --}}
        <p style="margin:0 0 14px;">
            {{-- trans_markup escapes the address as part of the sentence, so
                 it is not escaped again here. --}}
            {{ trans_markup('client.email_verify.sent_to', ['email' => '<strong>'.auth()->user()->email.'</strong>']) }}
        </p>

        @if($errors->has('code'))
        <div class="pn-alert pn-alert-error">{{ $errors->first('code') }}</div>
        @endif

        <form method="POST" action="{{ route('client.verification.code') }}" style="margin:0 0 14px;">
            @csrf
            <div class="form-group">
                <label class="form-label" for="code">{{ __('client.email_verify.code_label') }}</label>
                <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="12" class="form-control" style="max-width:220px; font-size:20px; letter-spacing:6px; text-align:center;">
            </div>
            <button type="submit" class="btn btn-primary">{{ __('client.email_verify.code_submit') }}</button>
        </form>

        <p style="margin:0 0 18px;color:var(--muted);font-size:13px;">
            {{ __('client.email_verify.hint', ['minutes' => \App\Http\Controllers\Client\EmailVerificationController::CODE_MINUTES]) }}
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <form method="POST" action="{{ route('client.verification.send') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-default">{{ __('client.email_verify.resend') }}</button>
            </form>

            {{-- The way out of a wrong address, which resending cannot fix. --}}
            <a href="{{ route('client.account.profile') }}" class="btn btn-default">{{ __('client.email_verify.change_email') }}</a>
        </div>
    </div>
</div>

@endsection
