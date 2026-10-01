@extends('client.layouts.app')
@section('title', __('client.account_users.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.account_users.title') }}</h1>
</div>
<p style="color:var(--muted); font-size:13px; margin:-6px 0 16px;">{{ __('client.account_users.intro') }}</p>

@if($errors->any())
<div class="pn-alert pn-alert-error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="pn-card" style="margin-bottom:16px;">
    <div class="pn-card-header">{{ __('client.account_users.logins') }}</div>
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <thead><tr><th>{{ __('common.form.email') }}</th><th>{{ __('client.account_users.permissions') }}</th><th></th></tr></thead>
            <tbody>
                @foreach($logins as $login)
                @php($granted = \App\Support\ClientPermissions::granted($login, $client) ?? [])
                <tr>
                    <td>
                        <div style="font-weight:500; font-size:13px;">{{ trim($login->first_name.' '.$login->last_name) ?: $login->email }}</div>
                        <div style="font-size:12px; color:var(--muted);">{{ $login->email }}</div>
                    </td>
                    <td style="font-size:12px;">
                        @if($login->pivot->owner)
                        <strong>{{ __('client.account_users.owner') }}</strong>
                        @else
                        <form method="POST" action="{{ route('client.account.users.update', $login) }}" style="margin:0;">
                            @csrf @method('PUT')
                            <div style="display:flex; flex-wrap:wrap; gap:4px 12px;">
                                @foreach($permissionNames as $perm)
                                <label style="display:flex; align-items:center; gap:4px; cursor:pointer;"><input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, $granted, true))> {{ __('client.account_users.perm_'.$perm) }}</label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-outline btn-xs" style="margin-top:6px;">{{ __('common.actions.save_changes') }}</button>
                        </form>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        @if(! $login->pivot->owner && $login->id !== auth()->id())
                        <form method="POST" action="{{ route('client.account.users.destroy', $login) }}" style="margin:0;" onsubmit="return confirm('{{ __('client.account_users.confirm_remove') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('client.account_users.remove') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($invites->isNotEmpty())
<div class="pn-card" style="margin-bottom:16px;">
    <div class="pn-card-header">{{ __('client.account_users.pending') }}</div>
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <tbody>
                @foreach($invites as $invite)
                <tr>
                    <td style="font-size:13px;">{{ $invite->email }}</td>
                    <td style="font-size:12px; color:var(--muted);">{{ __('client.account_users.sent_on', ['date' => $invite->created_at->format(date_fmt())]) }}</td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('client.account.users.invites.destroy', $invite) }}" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-xs">{{ __('client.account_users.cancel_invite') }}</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="pn-card">
    <div class="pn-card-header">{{ __('client.account_users.invite') }}</div>
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.account.users.invite') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('common.form.email') }}<span style="color:#c43c35;">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-control" style="max-width:420px;">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('client.account_users.permissions') }}</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px 14px; font-size:13px;">
                    @foreach($permissionNames as $perm)
                    <label style="display:flex; align-items:center; gap:4px; cursor:pointer;"><input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, old('permissions', $permissionNames), true))> {{ __('client.account_users.perm_'.$perm) }}</label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.account_users.send_invite') }}</button>
            <div style="font-size:12px; color:var(--muted); margin-top:8px;">{{ __('client.account_users.invite_hint', ['days' => \App\Models\UserInvite::VALID_DAYS]) }}</div>
        </form>
    </div>
</div>

@endsection
