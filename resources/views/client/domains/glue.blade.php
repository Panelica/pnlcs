@extends('client.layouts.app')
@section('title', __('client.domains.glue_title').' - '.$domain->domain)
@section('content')
<div class="pn-page-header">
    <div>
        <h1>{{ __('client.domains.glue_title') }}</h1>
        <p class="text-muted text-sm">{{ $domain->domain }}</p>
    </div>
    <a href="{{ route('client.domains.show', $domain) }}" class="btn btn-default">{{ __('common.actions.back') }}</a>
</div>

<div class="pn-card" style="margin-bottom:16px;">
    <div class="pn-card-body">
        <p class="text-muted text-sm" style="margin-top:0;">{{ __('client.domains.glue_hint', ['domain' => $domain->domain]) }}</p>
        @if($hosts === null)
        <p class="text-sm" style="color:var(--warning);">{{ __('client.domains.glue_unreadable') }}</p>
        @elseif($hosts === [])
        <p class="text-muted text-sm">{{ __('client.domains.glue_none') }}</p>
        @else
        <table class="pn-table">
            <thead><tr><th>{{ __('client.domains.glue_host') }}</th><th>{{ __('client.domains.glue_ips') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($hosts as $h)
            <tr>
                <td style="font-family:monospace;">{{ $h['host'] }}</td>
                <td style="font-family:monospace;font-size:12px;">{{ implode(', ', $h['ips']) }}</td>
                <td style="text-align:right;">
                    <form method="POST" action="{{ route('client.domains.glue.delete', $domain) }}" onsubmit="return pnConfirm(event, @js(__('client.domains.glue_confirm_delete')))" style="display:inline;">
                        @csrf @method('DELETE')
                        <input type="hidden" name="host" value="{{ $h['host'] }}">
                        <button type="submit" class="btn btn-outline btn-xs">{{ __('common.actions.delete') }}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header">{{ __('client.domains.glue_add') }}</div>
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.domains.glue.save', $domain) }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="g-host">{{ __('client.domains.glue_host') }}</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <input id="g-host" name="host" class="form-control" value="{{ old('host') }}" required maxlength="100">
                        <span class="text-muted text-sm">.{{ $domain->domain }}</span>
                    </div>
                    @error('host')<div class="text-sm" style="color:var(--danger);margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="g-ipv4">{{ __('client.domains.glue_ipv4') }}</label>
                    <input id="g-ipv4" name="ipv4" class="form-control" value="{{ old('ipv4') }}" required>
                    @error('ipv4')<div class="text-sm" style="color:var(--danger);margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="g-ipv6">{{ __('client.domains.glue_ipv6') }} <span class="text-muted">({{ __('client.form.optional') }})</span></label>
                    <input id="g-ipv6" name="ipv6" class="form-control" value="{{ old('ipv6') }}">
                    @error('ipv6')<div class="text-sm" style="color:var(--danger);margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>
            <p class="text-muted text-sm">{{ __('client.domains.glue_save_hint') }}</p>
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save') }}</button>
        </form>
    </div>
</div>
@endsection
