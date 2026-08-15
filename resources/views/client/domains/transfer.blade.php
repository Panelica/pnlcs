@extends("client.layouts.app")
@section("title", __("client.domains.transfer_domain"))
@section("content")
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.domains.transfer_heading') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.domains.transfer_subtitle') }}</p>
    </div>
</div>

<div class="pn-card" style="max-width:560px;">
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.cart.add-domain') }}">
            @csrf
            <input type="hidden" name="type" value="transfer">
            <input type="hidden" name="years" value="1">

            <div class="form-group">
                <label class="form-label" for="domain">{{ __('client.domains.domain_name') }}</label>
                <input type="text" id="domain" name="domain" value="{{ old('domain', $domain) }}" required
                       placeholder="example.com" class="form-control" style="font-family:monospace;">
                @error('domain')<div class="text-danger text-sm" style="margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label" for="epp_code">{{ __('client.domains.epp_code') }}</label>
                <input type="text" id="epp_code" name="epp_code" value="{{ old('epp_code') }}" required
                       class="form-control" autocomplete="off">
                <div class="text-muted text-sm" style="margin-top:4px;">{{ __('client.domains.epp_code_transfer_hint') }}</div>
                @error('epp_code')<div class="text-danger text-sm" style="margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <div style="margin-top:20px;display:flex;gap:8px;align-items:center;">
                <button type="submit" class="btn btn-primary">{{ __('client.domains.submit_transfer') }}</button>
                <a href="{{ route('client.domain.search') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
