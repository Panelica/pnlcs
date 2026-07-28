<x-mail::message>
# {{ __('email.ssl_expiring.title') }}

{{ __('email.ssl_expiring.hello', ['name' => $order->client->first_name ?? __('email.common.customer')]) }}

{{ __('email.ssl_expiring.body', ['days' => $daysRemaining]) }}

**{{ __('email.ssl_expiring.certificate_details') }}**
- **{{ __('email.common.domain_label') }}:** {{ $order->domain }}
- **{{ __('email.ssl_expiring.expires') }}:** {{ $order->crt_expires?->format(date_fmt()) }}

{{ __('email.ssl_expiring.renew_recommend') }}

<x-mail::button :url="$viewUrl">
{{ __('email.ssl_expiring.view_button') }}
</x-mail::button>

{{ __('email.ssl_expiring.questions') }}

{{ __('email.ssl_expiring.thanks') }}<br>
{{ $companyName }}
</x-mail::message>