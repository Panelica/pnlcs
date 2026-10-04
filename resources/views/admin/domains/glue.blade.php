@extends('admin.layouts.app')
@section('title', __('client.domains.glue_title').' - '.$domain->domain)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h1>{{ __('client.domains.glue_title') }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;font-family:monospace;">{{ $domain->domain }}</div>
    </div>
    <a href="{{ route('admin.domains.show', $domain) }}" class="btn btn-default btn-sm">&larr; {{ __('admin.domains.back') }}</a>
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <p style="font-size:13px;color:#777;margin-top:0;">{{ __('client.domains.glue_hint', ['domain' => $domain->domain]) }}</p>
        @if($hosts === null)
        <p style="font-size:13px;color:#8a6d3b;">{{ __('client.domains.glue_unreadable') }}</p>
        @elseif($hosts === [])
        <p style="font-size:13px;color:#777;">{{ __('client.domains.glue_none') }}</p>
        @else
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead><tr><th style="text-align:left;padding:6px 0;">{{ __('client.domains.glue_host') }}</th><th style="text-align:left;">{{ __('client.domains.glue_ips') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($hosts as $h)
            <tr style="border-top:1px solid #eee;">
                <td style="font-family:monospace;padding:6px 0;">{{ $h['host'] }}</td>
                <td style="font-family:monospace;font-size:12px;">{{ implode(', ', $h['ips']) }}</td>
                <td style="text-align:right;">
                    <form method="POST" action="{{ route('admin.domains.glue.delete', $domain) }}" onsubmit="return confirm('{{ __('client.domains.glue_confirm_delete') }}')" style="display:inline;">
                        @csrf @method('DELETE')
                        <input type="hidden" name="host" value="{{ $h['host'] }}">
                        <button type="submit" class="btn btn-default btn-sm">{{ __('common.actions.delete') }}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('client.domains.glue_add') }}</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.domains.glue.save', $domain) }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="g-host">{{ __('client.domains.glue_host') }}</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <input id="g-host" name="host" class="form-control" value="{{ old('host') }}" required maxlength="100">
                        <span style="font-size:13px;color:#777;">.{{ $domain->domain }}</span>
                    </div>
                    @error('host')<div style="color:#a94442;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="g-ipv4">{{ __('client.domains.glue_ipv4') }}</label>
                    <input id="g-ipv4" name="ipv4" class="form-control" value="{{ old('ipv4') }}" required>
                    @error('ipv4')<div style="color:#a94442;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="g-ipv6">{{ __('client.domains.glue_ipv6') }}</label>
                    <input id="g-ipv6" name="ipv6" class="form-control" value="{{ old('ipv6') }}">
                    @error('ipv6')<div style="color:#a94442;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>
            <p style="font-size:13px;color:#777;">{{ __('client.domains.glue_save_hint') }}</p>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
        </form>
    </div>
</div>
@endsection
