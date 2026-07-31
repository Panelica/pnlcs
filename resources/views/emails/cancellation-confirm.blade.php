<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $service->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.cancellation_confirm.confirmed') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.product_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.domain_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->domain ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.cancellation_confirm.cancellation_type') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $cancellationType === 'immediate' ? __('email.cancellation_confirm.immediate') : __('email.cancellation_confirm.end_of_period') }}</td></tr>
@if($cancellationType !== 'immediate' && $service->next_due_date)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.cancellation_confirm.effective_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->next_due_date->format(date_fmt()) }}</td></tr>
@endif
</table>

@if($cancellationType === 'immediate')
<p>{{ __('email.cancellation_confirm.terminated_shortly') }}</p>
@else
<p>{{ __('email.cancellation_confirm.active_until_end') }}</p>
@endif

<p>{{ __('email.cancellation_confirm.change_mind') }}</p>

@include('emails.partials.action', ['url' => route('client.services.show', $service->id), 'label' => __('email.common.view_service')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>