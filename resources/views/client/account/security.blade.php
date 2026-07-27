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
        @else
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f5f5f5; border:1px solid #e0e0e0; border-radius:4px; margin-bottom:14px;">
            <span style="font-size:13px; color:#777;">{{ __('client.security.2fa_not_enabled') }}</span>
            <a href="{{ route('client.2fa.enable') }}" class="btn btn-success btn-sm">{{ __('client.auth.enable_2fa_btn') }}</a>
        </div>
        @endif
    </div>
</div>

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
                        <div style="font-size:12px; color:#777;">{{ $session->user_agent ? Str::limit($session->user_agent, 60) : '-' }}</div>
                    </td>
                    <td style="color:#777; font-size:12px;">{{ $session->last_activity ? \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() : '-' }}</td>
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
                    <td colspan="3" style="text-align:center; padding:24px; color:#999;">{{ __('client.security.no_sessions') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
