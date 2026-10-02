@extends('client.layouts.app')
@section('title', __('client.security.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.security.title') }}</h1>
</div>

<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">{{ __('client.security.two_factor') }}</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:16px;">
            {{ __('client.security.2fa_desc') }}
        </p>
        @if($twoFactorEnabled ?? false)
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#dff0d8; border:1px solid #d6e9c6; border-radius:4px; margin-bottom:14px;">
            <span style="font-size:13px; color:#3c763d; font-weight:500;">&#10003; {{ __('client.security.2fa_enabled') }}</span>
            <form method="POST" action="{{ route('client.2fa.disable') }}" style="margin:0;">
                @csrf
                <input type="password" name="password" placeholder="{{ __('client.security.your_password') }}" class="form-control form-control-sm" style="width:160px;display:inline-block;margin-right:6px;" required>
                <button type="submit" class="btn btn-danger btn-sm">{{ __('common.actions.disable') }}</button>
            </form>
        </div>
        {{-- The backup codes are flashed here once, right after 2FA is switched
             on (AuthController::enable2fa). Nothing showed them, so a customer
             who lost their phone held codes they had never seen. --}}
        @if(is_array(session('backup_codes')) && session('backup_codes'))
        <div style="padding:12px 14px; border:1px solid #faebcc; background:#fcf8e3; border-radius:4px; margin-bottom:14px;">
            <div style="font-size:13px; font-weight:600; margin-bottom:6px;">{{ __('client.security.backup_codes_title') }}</div>
            <p style="font-size:12.5px; margin:0 0 10px;">{{ __('client.security.backup_codes_hint') }}</p>
            <ul style="list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:6px; font-family:ui-monospace, Menlo, monospace; font-size:14px;">
                @foreach(session('backup_codes') as $backupCode)
                <li>{{ $backupCode }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        @else
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:var(--bg); border:1px solid #e0e0e0; border-radius:4px; margin-bottom:14px;">
            <span style="font-size:13px; color:var(--muted);">{{ __('client.security.2fa_not_enabled') }}</span>
            <a href="{{ route('client.2fa.enable') }}" class="btn btn-success btn-sm">{{ __('client.auth.enable_2fa_btn') }}</a>
        </div>
        @endif
    </div>
</div>

@if($phoneVerifyAvailable ?? false)
<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">{{ __('client.phone_verify.title') }}</div>
    <div class="pn-card-body">
        @if($client?->phone_verified_at)
        <div style="padding:12px 14px; background:#dff0d8; border:1px solid #d6e9c6; border-radius:4px;">
            <span style="font-size:13px; color:#3c763d; font-weight:500;">&#10003; {{ __('client.phone_verify.verified_label') }} ({{ $client->full_phone }})</span>
        </div>
        @elseif(! $client?->full_phone)
        <p style="font-size:13px; color:var(--muted); margin:0;">{{ __('client.phone_verify.no_phone') }}</p>
        @elseif(session('phone_code_sent'))
        <p style="font-size:13px; color:#555; margin-bottom:12px;">{{ __('client.phone_verify.enter_code_hint') }}</p>
        <form method="POST" action="{{ route('client.account.phone.verify_check') }}" style="display:flex; gap:8px; align-items:center; margin:0;">
            @csrf
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('client.phone_verify.code_placeholder') }}" class="form-control form-control-sm" style="width:140px;" required>
            <button type="submit" class="btn btn-success btn-sm">{{ __('client.phone_verify.confirm_btn') }}</button>
        </form>
        @error('code')<p style="font-size:12px;color:#a94442;margin:8px 0 0;">{{ $message }}</p>@enderror
        @else
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <span style="font-size:13px; color:var(--muted);">{{ __('client.phone_verify.unverified_label') }} ({{ $client->full_phone }})</span>
            <form method="POST" action="{{ route('client.account.phone.verify') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ __('client.phone_verify.send_btn') }}</button>
            </form>
        </div>
        @error('phone')<p style="font-size:12px;color:#a94442;margin:8px 0 0;">{{ $message }}</p>@enderror
        @endif
    </div>
</div>
@endif

@if($sessionsSupported ?? false)
<div class="pn-card">
    <div class="pn-card-header">{{ __('client.security.active_sessions') }}</div>
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('client.security.device_ip') }}</th>
                    <th>{{ __('client.security.last_activity') }}</th>
                    <th>{{ __('client.security.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions ?? [] as $session)
                <tr>
                    <td>
                        <div style="font-weight:500; font-size:13px;">{{ $session->ip_address }}</div>
                        <div style="font-size:12px; color:var(--muted);">{{ $session->user_agent ? Str::limit($session->user_agent, 60) : '-' }}</div>
                    </td>
                    <td style="color:var(--muted); font-size:12px;">{{ $session->last_activity ? \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() : '-' }}</td>
                    <td>
                        @if($session->id !== session()->getId())
                        <form method="POST" action="{{ route('client.account.security.logout_session', $session->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('client.security.revoke') }}</button>
                        </form>
                        @else
                        <span style="font-size:12px; color:#46a546; font-weight:500;">{{ __('client.security.current') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align:center; padding:24px; color:var(--muted);">{{ __('client.security.no_sessions') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="pn-card">
    <div class="pn-card-header">{{ __('client.security.login_history') }}</div>
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('client.security.login_when') }}</th>
                    <th>{{ __('client.security.device_ip') }}</th>
                    <th>{{ __('client.security.login_result') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logins ?? [] as $login)
                <tr>
                    <td style="font-size:13px; white-space:nowrap;">{{ $login->created_at?->format(date_fmt().' H:i') }}</td>
                    <td>
                        <div style="font-weight:500; font-size:13px;">{{ $login->ip_address ?: '-' }}</div>
                        <div style="font-size:12px; color:var(--muted);">{{ \App\Services\LoginRecorder::describe($login->user_agent) }}@if($login->method === 'google') · {{ __('client.security.via_google') }}@endif</div>
                    </td>
                    <td>
                        @if($login->successful)
                        <span style="font-size:12px; color:#46a546; font-weight:500;">{{ __('client.security.login_ok') }}</span>
                        @else
                        <span style="font-size:12px; color:#c0392b; font-weight:500;">{{ __('client.security.login_failed') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align:center; padding:24px; color:var(--muted);">{{ __('client.security.no_logins') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div style="font-size:12px; color:var(--muted); padding:10px 16px;">{{ __('client.security.login_history_hint') }}@if(\App\Services\LoginRecorder::mailEnabled()) {{ __('client.security.login_history_mail') }}@endif</div>
    </div>
</div>

@endsection
