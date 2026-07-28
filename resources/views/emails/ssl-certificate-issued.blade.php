<x-mail::message>
# {{ __('email.ssl_issued.title') }}

{{ __('email.ssl_issued.hello', ['name' => $order->client->first_name ?? __('email.common.customer')]) }}

{{ __('email.ssl_issued.body') }}

**{{ __('email.ssl_issued.certificate_details') }}**
- **{{ __('email.common.domain_label') }}:** {{ $order->domain }}
- **{{ __('email.ssl_issued.issued') }}:** {{ $order->completion_date?->format(date_fmt()) }}
- **{{ __('email.ssl_issued.expires') }}:** {{ $order->crt_expires?->format(date_fmt()) }}

<x-mail::button :url="$downloadUrl">
{{ __('email.ssl_issued.download_button') }}
</x-mail::button>

<x-mail::button :url="$viewUrl" color="secondary">
{{ __('email.ssl_issued.view_button') }}
</x-mail::button>

{{ __('email.ssl_issued.download_includes') }}
- {{ __('email.ssl_issued.include_crt') }}
- {{ __('email.ssl_issued.include_ca') }}
- {{ __('email.ssl_issued.include_fullchain') }}
- {{ __('email.ssl_issued.include_key') }}

{{ __('email.ssl_issued.install_asap') }}

{{ __('email.ssl_issued.thanks') }}<br>
{{ $companyName }}
</x-mail::message>