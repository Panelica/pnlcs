@extends('client.layouts.app')
@section('title', 'Enable Two-Factor Authentication')
@section('content')
<a href="{{ route('client.account.security') }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Back to Security
</a>

<div class="pn-page-header"><div><h1 class="pn-page-title">Enable Two-Factor Authentication</h1></div></div>

<div class="pn-card" style="max-width:600px;">
    <div class="pn-card-header"><span class="pn-card-title">Setup</span></div>
    <div class="pn-card-body">
        <p style="margin-bottom:16px;">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):</p>

        <div style="text-align:center;margin:20px 0;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode(\$qrUrl) }}" alt="QR Code" style="border:4px solid var(--border,#eee);border-radius:8px;">
        </div>

        <p style="text-align:center;font-size:13px;color:var(--muted);">Or enter this code manually:</p>
        <div style="text-align:center;margin-bottom:20px;">
            <code style="font-size:16px;background:var(--bg-alt,#f5f5f5);padding:8px 16px;border-radius:6px;letter-spacing:2px;">{{ \$secret }}</code>
        </div>

        @if(\$errors->any())
        <div class="pn-alert pn-alert-danger" style="margin-bottom:16px;">{{ \$errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('client.2fa.enable') }}">
            @csrf
            <div style="margin-bottom:16px;">
                <label class="pn-label">Enter the 6-digit code to verify:</label>
                <input type="text" name="code" class="pn-input" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="000000" style="max-width:200px;text-align:center;font-size:18px;letter-spacing:4px;">
            </div>
            <button type="submit" class="pn-btn pn-btn-primary">Enable 2FA</button>
            <a href="{{ route('client.account.security') }}" class="pn-btn" style="margin-left:8px;">Cancel</a>
        </form>
    </div>
</div>
@endsection
