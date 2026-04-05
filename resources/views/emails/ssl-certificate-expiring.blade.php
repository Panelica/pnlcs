<x-mail::message>
# SSL Certificate Expiring Soon

Hello {{ $order->client->first_name ?? 'Customer' }},

Your SSL certificate is expiring in **{{ $daysRemaining }} day(s)**.

**Certificate Details:**
- **Domain:** {{ $order->domain }}
- **Expires:** {{ $order->crt_expires?->format('d M Y') }}

To avoid any service interruption, we recommend renewing your certificate as soon as possible.

<x-mail::button :url="$viewUrl">
View Certificate Details
</x-mail::button>

If you have any questions about the renewal process, please contact our support team.

Thanks,<br>
{{ $companyName }}
</x-mail::message>
