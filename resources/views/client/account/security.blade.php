@extends('client.layouts.app')
@section('title', 'Security Settings')
@section('content')

<div class="page-header">
    <h1>Security Settings</h1>
</div>

<div class="pn-card" style="margin-bottom:20px;">
    <div class="pn-card-header">Two-Factor Authentication</div>
    <div class="pn-card-body">
        <p style="font-size:13px; color:#555; margin-bottom:16px;">
            Two-factor authentication adds an extra layer of security to your account by requiring both your password and a verification code from your phone.
        </p>
        @if($twoFactorEnabled ?? false)
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#dff0d8; border:1px solid #d6e9c6; border-radius:4px; margin-bottom:14px;">
            <span style="font-size:13px; color:#3c763d; font-weight:500;">&#10003; Two-Factor Authentication is enabled</span>
            <form method="POST" action="{{ route('client.2fa.disable') }}" style="margin:0;">
                @csrf
                <input type="password" name="password" placeholder="Your password" class="form-control form-control-sm" style="width:160px;display:inline-block;margin-right:6px;" required>
                <button type="submit" class="btn btn-danger btn-sm">{{ __('common.actions.disable') }}</button>
            </form>
        </div>
        @else
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f5f5f5; border:1px solid #e0e0e0; border-radius:4px; margin-bottom:14px;">
            <span style="font-size:13px; color:#777;">Two-Factor Authentication is not enabled</span>
            <a href="{{ route('client.2fa.enable') }}" class="btn btn-success btn-sm">Enable 2FA</a>
        </div>
        @endif
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header">Active Sessions</div>
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>Device / IP</th>
                    <th>Last Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions ?? [] as $session)
                <tr>
                    <td>
                        <div style="font-weight:500; font-size:13px;">{{ $session->ip_address }}</div>
                        <div style="font-size:12px; color:#777;">{{ $session->user_agent ? Str::limit($session->user_agent, 60) : '-' }}</div>
                    </td>
                    <td style="color:#777; font-size:12px;">{{ $session->last_activity ? \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() : '-' }}</td>
                    <td>
                        @if($session->id !== session()->getId())
                        <form method="POST" action="{{ route('client.account.security.logout_session', $session->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-xs">Revoke</button>
                        </form>
                        @else
                        <span style="font-size:12px; color:#46a546; font-weight:500;">Current</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align:center; padding:24px; color:#999;">No active sessions.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
