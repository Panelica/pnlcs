<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.ksef_issued.body', ['number' => $invoice->invoice_num ?? $invoice->id]) }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr>
    <td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') ?? 'Invoice Number' }}</strong></td>
    <td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td>
</tr>
<tr>
    <td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.ksef_issued.ksef_number') }}</strong></td>
    <td style="padding:8px;border-bottom:1px solid #eee;">{{ $ksefNumber }}</td>
</tr>
</table>

<p style="font-size:13px;color:#555;">{{ $companyName }}</p>
</body></html>
