<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? __('email.common.customer')]) }}</p>

<p style="color:#dc3545;"><strong>{{ __('email.invoice_overdue.overdue_notice', ['number' => $invoice->invoice_num ?? $invoice->id, 'days' => $daysOverdue]) }}</strong></p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.invoice_overdue.amount_due') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ money_fmt($invoice->total) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.due_date_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->due_date?->format(date_fmt()) ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.invoice_overdue.days_overdue') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;color:#dc3545;"><strong>{{ $daysOverdue }}</strong></td></tr>
</table>

<p>{{ __('email.invoice_overdue.settle_soon') }}</p>

<p>{{ __('email.invoice_overdue.login_to_pay') }}</p>

@include('emails.partials.action', ['url' => route('client.invoices.show', $invoice->id), 'label' => __('email.common.pay_invoice')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>