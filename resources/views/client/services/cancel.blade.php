@extends('client.layouts.app')
@section('title', __('client.services.request_cancellation'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.services.request_cancellation') }}</h1>
    <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; {{ __('client.services.back_to_service') }}</a>
</div>

<div style="background:#fcf8e3; border:1px solid #faebcc; color:#8a6d3b; padding:12px 16px; border-radius:4px; font-size:13px; margin-bottom:20px;">
    <strong>{{ __('client.actions.warning') }}:</strong> {{ __('client.services.cancel_warning') }}: <strong>{{ $service->product?->name ?? 'Service' }}</strong>
    @if($service->domain) &mdash; {{ $service->domain }}@endif
</div>

<div class="pn-card">
    <div class="pn-card-header">{{ __('client.services.cancellation_request') }}</div>
    <div class="pn-card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.services.cancel.submit', $service) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('client.services.cancellation_type') }} <span style="color:#c43c35;">*</span></label>
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid var(--border); border-radius:4px; cursor:pointer;">
                        <input type="radio" name="type" value="Immediate" {{ old('type') === 'Immediate' ? 'checked' : '' }} required style="margin-top:2px;">
                        <div>
                            <div style="font-weight:500; font-size:13px;">{{ __('client.services.immediate') }}</div>
                            <div style="font-size:12px; color:var(--muted); margin-top:2px;">{{ __('client.services.immediate_desc') }}</div>
                        </div>
                    </label>
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid var(--border); border-radius:4px; cursor:pointer;">
                        <input type="radio" name="type" value="End of Billing Period" {{ old('type') === 'End of Billing Period' ? 'checked' : '' }} style="margin-top:2px;">
                        <div>
                            <div style="font-weight:500; font-size:13px;">{{ __('client.services.end_of_period') }}</div>
                            <div style="font-size:12px; color:var(--muted); margin-top:2px;">{{ __('client.services.end_of_period_desc') }} {{ $service->next_due_date?->format(date_fmt()) ?? __('client.services.end_of_period_fallback') }}.</div>
                        </div>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="reason">{{ __('client.services.cancellation_reason') }}</label>
                <textarea id="reason" name="reason" rows="4" class="form-control" placeholder="{{ __('client.services.cancellation_reason_placeholder') }}">{{ old('reason') }}</textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-danger">{{ __('client.services.submit_cancellation') }}</button>
                <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

@endsection
