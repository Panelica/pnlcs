<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.auto_charge_action_required.intro', ['amount' => money_fmt($amount), 'number' => $invoice->invoice_num ?? $invoice->id]) }}</p>

<p>{{ __('email.auto_charge_action_required.nothing_taken') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.amount_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ money_fmt($amount) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.due_date_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->due_date?->format(date_fmt()) ?? '—' }}</td></tr>
</table>

<p>{{ __('email.auto_charge_action_required.what_to_do') }}</p>

@include('emails.partials.action', ['url' => route('client.invoices.show', $invoice->id), 'label' => __('email.common.pay_invoice')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
