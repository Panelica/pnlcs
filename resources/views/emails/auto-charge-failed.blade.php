<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.auto_charge_failed.attempted', ['amount' => money_fmt($amount), 'number' => $invoice->invoice_num ?? $invoice->id]) }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.amount_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ money_fmt($amount) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.due_date_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->due_date?->format(date_fmt()) ?? '—' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.auto_charge_failed.card_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $paymentMethod->card_brand ? ucfirst($paymentMethod->card_brand).' ' : '' }}@if($paymentMethod->last_four)•••• {{ $paymentMethod->last_four }}@else{{ $paymentMethod->description ?: '—' }}@endif</td></tr>
</table>

@if($cardEnded)
<p>{{ __('email.auto_charge_failed.card_ended') }}</p>
@endif

@if($retryAt)
<p>{{ __('email.auto_charge_failed.will_retry', ['date' => $retryAt->format(date_fmt())]) }}</p>
@else
<p>{{ __('email.auto_charge_failed.given_up') }}</p>
<p>{{ __('email.auto_charge_failed.please_pay') }}</p>
@endif

@include('emails.partials.action', ['url' => route('client.invoices.show', $invoice->id), 'label' => __('email.common.pay_invoice')])

<p style="font-size:13px;"><a href="{{ route('client.payment-methods.index') }}" style="color:#405189;">{{ __('email.common.update_payment_method') }}</a></p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
