<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'HostCo') }} — Premium Web Hosting</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=open-sans:400,600,700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; color: #333; background: #fff; }
        a { text-decoration: none; }
        nav { background: #1A4D80; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; height: 60px; }
        .nav-brand { color: #fff; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
        .nav-links { display: flex; gap: 8px; }
        .btn { display: inline-block; padding: 9px 22px; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; }
        .btn-outline-white { color: #fff; border: 1px solid rgba(255,255,255,0.7); background: transparent; }
        .btn-outline-white:hover { background: rgba(255,255,255,0.1); }
        .btn-white { background: #fff; color: #1A4D80; }
        .btn-white:hover { background: #f0f4ff; }
        .btn-primary { background: #337ab7; color: #fff; }
        .btn-primary:hover { background: #2a6499; }
        .btn-primary-lg { background: #337ab7; color: #fff; padding: 13px 32px; font-size: 16px; border-radius: 4px; display: inline-block; font-weight: 600; }
        .btn-primary-lg:hover { background: #2a6499; color: #fff; }
        .btn-outline-primary { color: #337ab7; border: 2px solid #337ab7; background: transparent; padding: 11px 30px; font-size: 16px; border-radius: 4px; display: inline-block; font-weight: 600; }
        .btn-outline-primary:hover { background: #337ab7; color: #fff; }
        .hero { background: linear-gradient(135deg, #1A4D80 0%, #2563a8 50%, #337ab7 100%); color: #fff; padding: 80px 40px; text-align: center; }
        .hero h1 { font-size: 46px; font-weight: 700; margin-bottom: 16px; line-height: 1.2; }
        .hero p { font-size: 20px; opacity: 0.9; margin-bottom: 36px; max-width: 580px; margin-left: auto; margin-right: auto; }
        .hero-buttons { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; margin-bottom: 48px; }
        .domain-search { background: rgba(255,255,255,0.1); border-radius: 8px; padding: 24px; max-width: 700px; margin: 0 auto; }
        .domain-search h3 { font-size: 16px; font-weight: 600; margin-bottom: 14px; opacity: 0.9; }
        .domain-form { display: flex; gap: 8px; flex-wrap: wrap; }
        .domain-input { flex: 1; min-width: 200px; padding: 11px 14px; border: 1px solid rgba(255,255,255,0.4); border-radius: 4px; font-size: 15px; background: rgba(255,255,255,0.95); color: #333; }
        .domain-input:focus { outline: 2px solid #fff; }
        .domain-tld { padding: 11px 12px; border: 1px solid rgba(255,255,255,0.4); border-radius: 4px; background: rgba(255,255,255,0.95); color: #333; font-size: 15px; }
        .btn-search { background: #f0a500; color: #fff; border: none; padding: 11px 24px; border-radius: 4px; font-size: 15px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-search:hover { background: #d49000; }
        section { padding: 70px 40px; }
        .section-title { text-align: center; font-size: 30px; font-weight: 700; color: #1A4D80; margin-bottom: 10px; }
        .section-sub { text-align: center; font-size: 15px; color: #666; margin-bottom: 48px; }
        .pricing-bg { background: #f6f9fc; }
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1000px; margin: 0 auto; }
        @media (max-width: 900px) { .pricing-grid { grid-template-columns: 1fr; max-width: 400px; } }
        .plan-card { background: #fff; border: 1px solid #d5e5f5; border-radius: 8px; padding: 30px 24px; display: flex; flex-direction: column; text-align: center; position: relative; transition: box-shadow 0.2s, transform 0.2s; }
        .plan-card:hover { box-shadow: 0 8px 32px rgba(26,77,128,0.12); transform: translateY(-3px); }
        .plan-card.featured { border-color: #337ab7; border-width: 2px; }
        .plan-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #337ab7; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .plan-name { font-size: 18px; font-weight: 700; color: #1A4D80; margin-bottom: 6px; }
        .plan-price { font-size: 38px; font-weight: 700; color: #333; line-height: 1; }
        .plan-price sup { font-size: 18px; font-weight: 700; vertical-align: super; }
        .plan-price-cycle { font-size: 13px; color: #888; margin-bottom: 20px; margin-top: 4px; }
        .plan-features { list-style: none; text-align: left; margin-bottom: 24px; flex: 1; }
        .plan-features li { font-size: 13px; color: #555; padding: 5px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 8px; }
        .plan-features li::before { content: "✓"; color: #337ab7; font-weight: 700; flex-shrink: 0; }
        .plan-features li:last-child { border-bottom: none; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; max-width: 1000px; margin: 0 auto; }
        @media (max-width: 800px) { .features-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .features-grid { grid-template-columns: 1fr; } }
        .feature-box { text-align: center; padding: 28px 20px; background: #fff; border: 1px solid #e8f0f8; border-radius: 8px; }
        .feature-icon { font-size: 36px; margin-bottom: 14px; }
        .feature-title { font-size: 15px; font-weight: 700; color: #1A4D80; margin-bottom: 8px; }
        .feature-desc { font-size: 13px; color: #666; line-height: 1.6; }
        footer { background: #1A4D80; color: rgba(255,255,255,0.8); padding: 40px; }
        .footer-inner { max-width: 1000px; margin: 0 auto; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 24px; }
        .footer-brand { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .footer-tagline { font-size: 13px; }
        .footer-links { display: flex; flex-direction: column; gap: 8px; }
        .footer-links a { color: rgba(255,255,255,0.7); font-size: 13px; }
        .footer-links a:hover { color: #fff; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.15); margin-top: 30px; padding-top: 20px; text-align: center; font-size: 12px; opacity: 0.6; }
        .trust-bar { background: #fff; border-top: 3px solid #337ab7; padding: 18px 40px; }
        .trust-items { display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; }
        .trust-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; font-weight: 600; }
        .trust-item .icon { font-size: 18px; }
    </style>
</head>
<body>

<nav>
    <div class="nav-brand">{{ config('app.name', 'HostCo') }}</div>
    <div class="nav-links">
        <a href="{{ route('client.store') }}" class="btn btn-outline-white">Products</a>
        <a href="{{ route('client.login') }}" class="btn btn-white">Login</a>
    </div>
</nav>

<section class="hero">
    <h1>Premium Web Hosting<br>for Everyone</h1>
    <p>Fast, reliable, and secure hosting solutions backed by 24/7 expert support. Get your site online in minutes.</p>
    <div class="hero-buttons">
        <a href="{{ route('client.store') }}" class="btn-primary-lg">Get Started &rarr;</a>
        <a href="{{ route('client.login') }}" class="btn-outline-primary">Client Login</a>
    </div>
    <div class="domain-search">
        <h3>Find your perfect domain name</h3>
        <form action="{{ route('client.store') }}" method="GET" class="domain-form">
            <input type="text" name="domain" class="domain-input" placeholder="yourdomain" required>
            <select name="tld" class="domain-tld">
                <option value=".com">.com</option>
                <option value=".net">.net</option>
                <option value=".org">.org</option>
                <option value=".io">.io</option>
                <option value=".co">.co</option>
            </select>
            <button type="submit" class="btn-search">Search Domain</button>
        </form>
    </div>
</section>

<div class="trust-bar">
    <div class="trust-items">
        <div class="trust-item"><span class="icon">&#128737;</span> Free SSL Certificate</div>
        <div class="trust-item"><span class="icon">&#9889;</span> NVMe SSD Storage</div>
        <div class="trust-item"><span class="icon">&#127758;</span> 99.9% Uptime SLA</div>
        <div class="trust-item"><span class="icon">&#128172;</span> 24/7 Support</div>
        <div class="trust-item"><span class="icon">&#128260;</span> Daily Backups</div>
    </div>
</div>

<section class="pricing-bg">
    <div class="section-title">Simple, Transparent Pricing</div>
    <div class="section-sub">No hidden fees. No surprises. Choose the plan that fits your needs.</div>
    <div class="pricing-grid">
        @php
            $sharedPlans = $products->filter(function($p) {
                return $p->group && stripos($p->group->name, 'shared') !== false;
            })->values()->take(3);
            $idx = 0;
        @endphp
        @forelse($sharedPlans as $product)
        @php
            $pricing = $product->pricing->first();
            $monthlyPrice = null;
            if ($pricing) {
                $mval = (float)($pricing->monthly ?? -1);
                if ($mval > 0) { $monthlyPrice = $mval; }
            }
            $descParts = array_filter(array_map('trim', explode(',', $product->description ?? '')));
            $isFeatured = ($idx === 1);
            $idx++;
        @endphp
        <div class="plan-card {{ $isFeatured ? 'featured' : '' }}">
            @if($isFeatured)<div class="plan-badge">Most Popular</div>@endif
            <div class="plan-name">{{ $product->name }}</div>
            @if($monthlyPrice)
            <div class="plan-price"><sup>$</sup>{{ number_format($monthlyPrice, 2) }}</div>
            <div class="plan-price-cycle">per month</div>
            @else
            <div class="plan-price" style="font-size:20px; margin-bottom:0;">Contact Us</div>
            <div class="plan-price-cycle">&nbsp;</div>
            @endif
            <ul class="plan-features">
                @foreach($descParts as $feature)
                <li>{{ $feature }}</li>
                @endforeach
                <li>Free SSL Certificate</li>
                <li>Daily Backups</li>
                <li>24/7 Support</li>
            </ul>
            <a href="{{ route('client.store.configure', $product->slug) }}" class="btn btn-primary" style="width:100%; text-align:center; display:block;">Order Now &rarr;</a>
        </div>
        @empty
        <div style="grid-column:1/-1; text-align:center; color:#999; padding:40px;">No plans available at this time.</div>
        @endforelse
    </div>
    <div style="text-align:center; margin-top:32px;">
        <a href="{{ route('client.store') }}" style="color:#337ab7; font-size:14px; font-weight:600;">View all products &rarr;</a>
    </div>
</section>

<section>
    <div class="section-title">Everything You Need to Succeed Online</div>
    <div class="section-sub">Industry-leading infrastructure and tools to power your websites.</div>
    <div class="features-grid">
        <div class="feature-box">
            <div class="feature-icon">&#9889;</div>
            <div class="feature-title">99.9% Uptime Guarantee</div>
            <div class="feature-desc">Our enterprise-grade infrastructure ensures your website stays online around the clock with redundant power and network.</div>
        </div>
        <div class="feature-box">
            <div class="feature-icon">&#128274;</div>
            <div class="feature-title">Free SSL Certificate</div>
            <div class="feature-desc">Every hosting plan includes a free Let's Encrypt SSL certificate, automatically renewed to keep your site secure.</div>
        </div>
        <div class="feature-box">
            <div class="feature-icon">&#128338;</div>
            <div class="feature-title">24/7 Expert Support</div>
            <div class="feature-desc">Our team of hosting experts is available around the clock via ticket and live chat to resolve any issue fast.</div>
        </div>
        <div class="feature-box">
            <div class="feature-icon">&#128190;</div>
            <div class="feature-title">Daily Automated Backups</div>
            <div class="feature-desc">Your data is automatically backed up daily with 30-day retention. Restore with a single click any time.</div>
        </div>
        <div class="feature-box">
            <div class="feature-icon">&#128640;</div>
            <div class="feature-title">NVMe SSD Storage</div>
            <div class="feature-desc">Ultra-fast NVMe solid-state drives deliver blazing-fast I/O performance for your databases and files.</div>
        </div>
        <div class="feature-box">
            <div class="feature-icon">&#128295;</div>
            <div class="feature-title">One-Click Installs</div>
            <div class="feature-desc">Install WordPress, Joomla, Magento and 400+ other applications with a single click using our auto-installer.</div>
        </div>
    </div>
</section>

<section style="background: linear-gradient(135deg, #1A4D80, #337ab7); text-align: center; padding: 60px 40px;">
    <h2 style="color: #fff; font-size: 32px; margin-bottom: 14px;">Ready to launch your website?</h2>
    <p style="color: rgba(255,255,255,0.85); font-size: 16px; margin-bottom: 30px;">Join thousands of satisfied customers. Get started today.</p>
    <a href="{{ route('client.store') }}" class="btn btn-white" style="font-size: 16px; padding: 13px 36px; display:inline-block;">Get Started Now &rarr;</a>
</section>

<footer>
    <div class="footer-inner">
        <div>
            <div class="footer-brand">{{ config('app.name', 'HostCo') }}</div>
            <div class="footer-tagline">Premium hosting solutions for businesses<br>and individuals worldwide.</div>
        </div>
        <div>
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; color:rgba(255,255,255,0.5);">Services</div>
            <div class="footer-links">
                <a href="{{ route('client.store') }}">Shared Hosting</a>
                <a href="{{ route('client.store') }}">VPS Hosting</a>
                <a href="{{ route('client.store') }}">Dedicated Servers</a>
                <a href="{{ route('client.store') }}">Reseller Hosting</a>
            </div>
        </div>
        <div>
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; color:rgba(255,255,255,0.5);">Account</div>
            <div class="footer-links">
                <a href="{{ route('client.login') }}">Client Login</a>
                <a href="{{ route('client.register') }}">Register</a>
                <a href="{{ route('client.invoices.index') }}">My Invoices</a>
                <a href="{{ route('client.tickets.index') }}">Support Tickets</a>
            </div>
        </div>
        <div>
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; color:rgba(255,255,255,0.5);">Help</div>
            <div class="footer-links">
                <a href="{{ route('client.kb.index') }}">Knowledge Base</a>
                <a href="{{ route('client.announcements.index') }}">Announcements</a>
                <a href="{{ route('client.contact') }}">Contact Us</a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} {{ config('app.name', 'HostCo') }}. All rights reserved.
    </div>
</footer>

</body>
</html>
