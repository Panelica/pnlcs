<x-mail::message>
# SSL Certificate Issued

Hello {{ $order->client->first_name ?? 'Customer' }},

Great news! Your SSL certificate has been issued and is ready for download.

**Certificate Details:**
- **Domain:** {{ $order->domain }}
- **Issued:** {{ $order->completion_date?->format('d M Y') }}
- **Expires:** {{ $order->crt_expires?->format('d M Y') }}

<x-mail::button :url="$downloadUrl">
Download Certificate
</x-mail::button>

<x-mail::button :url="$viewUrl" color="secondary">
View Certificate Details
</x-mail::button>

Your download will include:
- Certificate file (.crt)
- CA Bundle (.ca-bundle)
- Full chain certificate (.fullchain.crt)
- Private key (.key) — if stored

Please install the certificate on your web server as soon as possible.

Thanks,<br>
{{ $companyName }}
</x-mail::message>
