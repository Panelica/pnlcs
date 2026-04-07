@extends('admin.layouts.app')
@section('title', __('admin.auth.enable_2fa_title'))
@section('content')
<div class="page-header"><h1>{{ __('admin.auth.enable_2fa_title') }}</h1></div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <p>{{ __('admin.auth.scan_qr_desc') }}</p>

        <div style="text-align:center;margin:20px 0;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}" alt="QR Code" style="border:4px solid #f0f0f0;border-radius:8px;">
        </div>

        <p style="text-align:center;font-size:13px;color:#888;">{{ __('admin.auth.enter_manually') }}</p>
        <div style="text-align:center;margin-bottom:20px;">
            <code style="font-size:16px;background:#f5f5f5;padding:8px 16px;border-radius:6px;letter-spacing:2px;">{{ $secret }}</code>
        </div>

        @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.2fa.enable') }}">
            @csrf
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">{{ __('admin.auth.verify_code_label') }}</label>
                <input type="text" name="code" class="form-control" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="000000" style="max-width:200px;text-align:center;font-size:18px;letter-spacing:4px;">
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.auth.enable_2fa_button') }}</button>
            <a href="{{ route('admin.my-account') }}" class="btn btn-default" style="margin-left:8px;">{{ __('common.actions.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
