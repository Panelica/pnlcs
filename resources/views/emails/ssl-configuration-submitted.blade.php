<x-mail::message>
# {{ __('email.ssl_config_submitted.title') }}

{{ __('email.ssl_config_submitted.hello', ['name' => $order->client->first_name ?? __('email.common.customer')]) }}

{{ __('email.ssl_config_submitted.body') }}

**{{ __('email.ssl_config_submitted.order_details') }}**
- **{{ __('email.common.domain_label') }}:** {{ $order->domain }}
- **{{ __('email.ssl_config_submitted.validation_method') }}:** {{ $order->validation_method }}
- **{{ __('email.common.status_label') }}:** {{ __('email.ssl_config_submitted.configuration_submitted') }}

@if($order->validation_method === 'EMAIL')
{{ __('email.ssl_config_submitted.email_validation', ['email' => $order->approver_email]) }}
@elseif($order->validation_method === 'HTTP')
{{ __('email.ssl_config_submitted.http_validation') }}
@elseif($order->validation_method === 'DNS')
{{ __('email.ssl_config_submitted.dns_validation') }}
@endif

{{ __('email.ssl_config_submitted.notified_when_issued') }}

{{ __('email.ssl_config_submitted.thanks') }}<br>
{{ $companyName }}
</x-mail::message>