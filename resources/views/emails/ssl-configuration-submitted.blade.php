<x-mail::message>
# SSL Certificate Configuration Submitted

Hello {{ $order->client->first_name ?? 'Customer' }},

Your SSL certificate configuration has been submitted to the Certificate Authority for processing.

**Order Details:**
- **Domain:** {{ $order->domain }}
- **Validation Method:** {{ $order->validation_method }}
- **Status:** Configuration Submitted

@if($order->validation_method === 'EMAIL')
A validation email will be sent to **{{ $order->approver_email }}**. Please check your inbox and approve the certificate request.
@elseif($order->validation_method === 'HTTP')
Please ensure the HTTP validation file is accessible on your web server.
@elseif($order->validation_method === 'DNS')
Please ensure the DNS CNAME record has been created for your domain.
@endif

You will be notified once your certificate has been issued.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
