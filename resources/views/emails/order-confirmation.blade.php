<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $order->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.order_confirmation.thank_you') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.order_confirmation.order_id') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">#{{ $order->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.date_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $order->created_at?->format(date_fmt()) ?? now()->format(date_fmt()) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.order_confirmation.payment_method') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $order->payment_method ?? 'N/A' }}</td></tr>
</table>

@if($order->items && count($order->items) > 0)
<h3 style="color:#405189;font-size:16px;">{{ __('email.order_confirmation.order_items') }}</h3>
<table style="width:100%;border-collapse:collapse;margin:10px 0;">
<tr style="background:#f8f9fa;">
<td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.item_label') }}</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.domain_label') }}</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;"><strong>{{ __('email.common.price_label') }}</strong></td>
</tr>
@foreach($order->items as $item)
<tr>
<td style="padding:8px;border-bottom:1px solid #eee;">{{ $item->product?->name ?? __('email.common.service') }}</td>
<td style="padding:8px;border-bottom:1px solid #eee;">{{ $item->domain ?? '-' }}</td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">{{ money_fmt((float)($item->price ?? 0)) }}</td>
</tr>
@endforeach
<tr style="background:#f8f9fa;">
<td colspan="2" style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.total_label') }}</strong></td>
<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;"><strong>{{ money_fmt($order->total) }}</strong></td>
</tr>
</table>
@endif

<p>{{ __('email.order_confirmation.setup_shortly') }}</p>

@include('emails.partials.action', ['url' => $order->invoice_id ? route('client.invoices.show', $order->invoice_id) : route('client.home'), 'label' => __('email.common.view_invoice')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>