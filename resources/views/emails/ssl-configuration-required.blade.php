<x-mail::message>
# {{ __('email.ssl_config_required.title') }}

{{ __('email.ssl_config_required.hello', ['name' => $order->client->first_name ?? __('email.common.customer')]) }}

{{ __('email.ssl_config_required.body') }}

**{{ __('email.ssl_config_required.order_details') }}**
- **{{ __('email.ssl_config_required.order_number') }}** {{ $order->id }}
- **{{ __('email.common.product_label') }}:** {{ $order->service?->product?->name ?? __('email.ssl_config_required.ssl_certificate') }}

{{ __('email.ssl_config_required.activate_text') }}

<x-mail::button :url="$configureUrl">
{{ __('email.ssl_config_required.configure_button') }}
</x-mail::button>

{{ __('email.ssl_config_required.csr_help') }}

{{ __('email.ssl_config_required.thanks') }}<br>
{{ $companyName }}
</x-mail::message>