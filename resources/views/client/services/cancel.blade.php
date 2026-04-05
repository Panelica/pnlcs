@extends('client.layouts.app')
@section('title', 'Request Cancellation')
@section('content')

<div class="page-header">
    <h1>Request Cancellation</h1>
    <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline btn-sm">&larr; Back to Service</a>
</div>

<div style="background:#fcf8e3; border:1px solid #faebcc; color:#8a6d3b; padding:12px 16px; border-radius:4px; font-size:13px; margin-bottom:20px;">
    <strong>Warning:</strong> Requesting a cancellation will terminate your service: <strong>{{ $service->product?->name ?? 'Service' }}</strong>
    @if($service->domain) &mdash; {{ $service->domain }}@endif
</div>

<div class="pn-card">
    <div class="pn-card-header">Cancellation Request</div>
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
                <label class="form-label">Cancellation Type <span style="color:#c43c35;">*</span></label>
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid #ddd; border-radius:4px; cursor:pointer;">
                        <input type="radio" name="type" value="Immediate" {{ old('type') === 'Immediate' ? 'checked' : '' }} required style="margin-top:2px;">
                        <div>
                            <div style="font-weight:500; font-size:13px;">Immediate</div>
                            <div style="font-size:12px; color:#777; margin-top:2px;">Cancel right away. Access terminates immediately.</div>
                        </div>
                    </label>
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid #ddd; border-radius:4px; cursor:pointer;">
                        <input type="radio" name="type" value="End of Billing Period" {{ old('type') === 'End of Billing Period' ? 'checked' : '' }} style="margin-top:2px;">
                        <div>
                            <div style="font-weight:500; font-size:13px;">End of Billing Period</div>
                            <div style="font-size:12px; color:#777; margin-top:2px;">Service remains active until {{ $service->next_due_date?->format('d M Y') ?? 'the end of the billing period' }}.</div>
                        </div>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="reason">Reason for Cancellation</label>
                <textarea id="reason" name="reason" rows="4" class="form-control" placeholder="Please let us know why you are cancelling...">{{ old('reason') }}</textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-danger">Submit Cancellation Request</button>
                <a href="{{ route('client.services.show', $service) }}" class="btn btn-outline">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

@endsection
