<!DOCTYPE html>
<html><head><meta charset=\"utf-8\"></head>
<body style=\"font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;\">
<h2 style=\"color:#405189;\">{{ \$companyName }}</h2>

<p>Dear {{ \$client->first_name ?? 'Customer' }},</p>

<p>Your credit card ending in <strong>{{ \$paymentMethod->last_four }}</strong> is expiring on <strong>{{ \$paymentMethod->expiry_date }}</strong>.</p>

<p>To avoid any service interruptions, please update your payment method in your account area.</p>

<p style=\"color:#888;font-size:12px;margin-top:30px;\">{{ \$companyName }}</p>
</body></html>
