<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $client->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.cc_expiry.card_expiring', ['last_four' => $paymentMethod->last_four, 'expiry_date' => $paymentMethod->expiry_date]) }}</p>

<p>{{ __('email.cc_expiry.update_payment') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>