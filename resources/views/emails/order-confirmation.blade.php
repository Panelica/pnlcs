<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $order->client?->first_name ?? 'Customer']) }}</p>

<p>Thank you for your order! Your order has been confirmed.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Order ID</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">#{{ $order->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $order->created_at?->format('M d, Y') ?? now()->format('M d, Y') }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Payment Method</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $order->payment_method ?? 'N/A' }}</td></tr>
</table>

@if($order->items && count($order->items) > 0)
<h3 style="color:#405189;font-size:16px;">Order Items</h3>
<table style="width:100%;border-collapse:collapse;margin:10px 0;">
<tr style="background:#f8f9fa;">
<td style="padding:8px;border-bottom:1px solid #eee;"><strong>Item</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;"><strong>Price</strong></td>
</tr>
@foreach($order->items as $item)
<tr>
<td style="padding:8px;border-bottom:1px solid #eee;">{{ $item->product?->name ?? 'Service' }}</td>
<td style="padding:8px;border-bottom:1px solid #eee;">{{ $item->domain ?? '-' }}</td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">${{ number_format((float)($item->price ?? 0), 2) }}</td>
</tr>
@endforeach
<tr style="background:#f8f9fa;">
<td colspan="2" style="padding:8px;border-bottom:1px solid #eee;"><strong>Total</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;"><strong>${{ number_format((float)$order->total, 2) }}</strong></td>
</tr>
</table>
@endif

<p>Your services will be set up shortly. You will receive a separate notification once provisioning is complete.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
