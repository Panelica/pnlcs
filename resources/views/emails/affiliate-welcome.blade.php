<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $client->first_name ?? 'Partner']) }}</p>

<p>Welcome to the <strong>{{ $companyName }}</strong> Affiliate Program!</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Commission Rate</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $affiliate->commission_rate ?? '0' }}%</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Commission Type</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ucfirst($affiliate->commission_type ?? 'percentage') }}</td></tr>
</table>

<h3 style="color:#405189;font-size:16px;">How It Works</h3>
<ol style="line-height:1.8;">
<li>Share your unique referral link with potential customers.</li>
<li>When someone signs up and makes a purchase through your link, you earn a commission.</li>
<li>Track your referrals and earnings from your client area.</li>
<li>Request a payout once you reach the minimum threshold.</li>
</ol>

<p>Log in to your client area to find your referral link and start earning.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
