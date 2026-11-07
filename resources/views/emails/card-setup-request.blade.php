<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $client->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.card_request.body') }}</p>

<p>{{ __('email.card_request.own_hands') }}</p>

@include('emails.partials.action', ['url' => $addCardUrl, 'label' => __('email.card_request.button')])

<p style="color:#888;font-size:12px;">{{ __('email.card_request.not_expected') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
