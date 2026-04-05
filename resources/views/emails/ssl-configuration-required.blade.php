<x-mail::message>
# SSL Certificate Configuration Required

Hello {{ $order->client->first_name ?? 'Customer' }},

Your SSL certificate order has been received and is awaiting configuration.

**Order Details:**
- **Order #:** {{ $order->id }}
- **Product:** {{ $order->service?->product?->name ?? 'SSL Certificate' }}

To activate your SSL certificate, you need to complete the configuration process by providing your CSR (Certificate Signing Request) and selecting a domain validation method.

<x-mail::button :url="$configureUrl">
Configure SSL Certificate
</x-mail::button>

If you need help generating a CSR, please contact our support team.

Thanks,<br>
{{ $companyName }}
</x-mail::message>
