<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $offer->toClient?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.domain_move.offered', ['domain' => $offer->domain?->domain, 'from' => $offer->fromClient?->full_name ?: $offer->fromClient?->email]) }}</p>

<p>{{ __('email.domain_move.how', ['date' => $offer->expires_at?->format(date_fmt())]) }}</p>

@include('emails.partials.action', ['url' => route('client.domains.index'), 'label' => __('email.domain_move.action')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ __('email.domain_move.not_expected') }}</p>
<p style="color:#888;font-size:12px;">{{ $companyName }}</p>
</body></html>
