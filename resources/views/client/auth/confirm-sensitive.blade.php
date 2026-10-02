@extends('client.layouts.app')
@section('title', __('client.confirm.title'))
@section('content')

<div class="pn-card" style="max-width:460px; margin:24px auto;">
    <div class="pn-card-header">{{ __('client.confirm.title') }}</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:var(--muted); margin-top:0;">{{ __('client.confirm.intro', ['email' => $email]) }}</p>

        @if($errors->any())
        <div class="pn-alert pn-alert-error">{{ $errors->first() }}</div>
        @endif

        @if($codeSent)
        <form method="POST" action="{{ route('client.confirm.verify') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="code">{{ __('client.confirm.code_label', ['minutes' => $minutes]) }}</label>
                <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="12" class="form-control" style="font-size:20px; letter-spacing:6px; text-align:center;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.confirm.verify') }}</button>
        </form>
        <form method="POST" action="{{ route('client.confirm.send') }}" style="margin-top:12px;">
            @csrf
            <button type="submit" class="btn btn-outline btn-xs">{{ __('client.confirm.resend') }}</button>
        </form>
        @else
        <form method="POST" action="{{ route('client.confirm.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.confirm.send') }}</button>
        </form>
        @endif
    </div>
</div>

@endsection
