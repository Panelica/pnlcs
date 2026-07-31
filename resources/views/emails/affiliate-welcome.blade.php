<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $client->first_name ?? __('email.common.partner')]) }}</p>

<p>{{ __('email.affiliate_welcome.welcome', ['company' => $companyName]) }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.affiliate_welcome.commission_rate') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $affiliate->pay_type === 'percentage' ? rtrim(rtrim(number_format((float) $affiliate->pay_amount, 2), '0'), '.').'%' : number_format((float) $affiliate->pay_amount, 2) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.affiliate_welcome.commission_type') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ucfirst($affiliate->pay_type ?? 'percentage') }}</td></tr>
</table>

<h3 style="color:#405189;font-size:16px;">{{ __('email.affiliate_welcome.how_it_works') }}</h3>
<ol style="line-height:1.8;">
<li>{{ __('email.affiliate_welcome.step1') }}</li>
<li>{{ __('email.affiliate_welcome.step2') }}</li>
<li>{{ __('email.affiliate_welcome.step3') }}</li>
<li>{{ __('email.affiliate_welcome.step4') }}</li>
</ol>

<p>{{ __('email.affiliate_welcome.start_earning') }}</p>

@include('emails.partials.action', ['url' => route('client.affiliates.index'), 'label' => __('email.common.go_to_account')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>