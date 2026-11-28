@extends('admin.layouts.app')
@section('title', __('admin.auth.enable_2fa_title'))
@section('content')
<div class="page-header"><h1>{{ __('admin.auth.enable_2fa_title') }}</h1></div>

<div class="card twofa-setup-card">
    <div class="card-body">
        <p class="twofa-intro">{{ __('admin.auth.scan_qr_desc') }}</p>

        <div class="twofa-setup">
            <div class="twofa-qr">
                <img src="{{ $qrImage }}" width="200" height="200" alt="QR Code">
            </div>

            <div class="twofa-manual">
                <p class="twofa-manual-label">{{ __('admin.auth.enter_manually') }}</p>
                <code class="twofa-secret">{{ $secret }}</code>

                @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.2fa.enable') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.auth.verify_code_label') }}</label>
                        <input type="text" name="code" class="form-control twofa-code" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="000000">
                    </div>
                    <div class="twofa-actions">
                        <button type="submit" class="btn btn-primary">{{ __('admin.auth.enable_2fa_button') }}</button>
                        <a href="{{ route('admin.my-account') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
