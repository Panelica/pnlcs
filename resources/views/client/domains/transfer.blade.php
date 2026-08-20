@extends("client.layouts.app")
@section("title", __("client.domains.transfer_domain"))

@section("styles")
<style>
    /* Same shape as the cart's configure screen: the work on the left, the
       orientation on the right, one grid holding them level. */
    .transfer-layout { display:grid; grid-template-columns: 1fr 340px; gap:24px; max-width:960px; margin:0 auto; align-items:start; }
    @media (max-width: 900px) { .transfer-layout { grid-template-columns: 1fr; } }
    .transfer-steps { list-style:none; counter-reset: step; padding:0; margin:0; }
    .transfer-steps li { counter-increment: step; display:flex; gap:12px; padding:10px 0; border-bottom:1px solid var(--border); font-size:13.5px; line-height:1.55; color:var(--text); }
    .transfer-steps li:last-child { border-bottom:none; }
    .transfer-steps li::before { content: counter(step); flex-shrink:0; width:24px; height:24px; border-radius:50%; background:var(--primary-light); color:var(--primary); font-weight:700; font-size:12.5px; display:flex; align-items:center; justify-content:center; margin-top:1px; }
</style>
@endsection

@section("content")
<div class="pn-page-header" style="max-width:960px;margin-left:auto;margin-right:auto;">
    <div>
        <h1 class="pn-page-title">{{ __('client.domains.transfer_heading') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.domains.transfer_subtitle') }}</p>
    </div>
</div>

<div class="transfer-layout">
    <div class="pn-card">
        <div class="pn-card-header">{{ __('client.domains.transfer_domain') }}</div>
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
                           class="form-control" autocomplete="off" style="font-family:monospace;">
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

    <div>
        {{-- The person on this page is mid-move between two companies, and the
             step that stalls is always the same one: getting the code out of
             the old registrar. Saying the whole path out loud is what makes
             the form feel safe to submit. --}}
        <div class="pn-card">
            <div class="pn-card-header">{{ __('client.domains.transfer_how_title') }}</div>
            <div class="pn-card-body" style="padding:18px 22px;">
                <ol class="transfer-steps">
                    <li>{{ __('client.domains.transfer_step_unlock') }}</li>
                    <li>{{ __('client.domains.transfer_step_epp') }}</li>
                    <li>{{ __('client.domains.transfer_step_paste') }}</li>
                </ol>
            </div>
        </div>
        <div class="pn-alert pn-alert-info" style="margin-top:16px;font-size:13px;line-height:1.55;">
            {{ __('client.domains.transfer_duration') }}
        </div>
    </div>
</div>
@endsection
