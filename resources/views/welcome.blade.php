<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'PNLCS') }} — Premium Web Hosting</title>
    <meta name="description" content="Enterprise-grade web hosting with 99.9% uptime, free SSL, NVMe SSD storage, and 24/7 expert support. Starting at just $4.99/mo.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1e293b;
            background: #fff;
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }

        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid transparent;
            transition: all 0.3s ease;
            height: 72px; display: flex; align-items: center;
        }
        .navbar.scrolled { border-bottom-color: #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .nav-brand { font-size: 24px; font-weight: 800; color: #1a4d80; letter-spacing: -0.5px; }
        .nav-links { display: flex; align-items: center; gap: 32px; }
        .nav-links a { font-size: 15px; font-weight: 500; color: #64748b; transition: color 0.2s; }
        .nav-links a:hover { color: #1a4d80; }
        .nav-right { display: flex; align-items: center; gap: 16px; }
        .nav-login { font-size: 15px; font-weight: 500; color: #64748b; transition: color 0.2s; }
        .nav-login:hover { color: #1a4d80; }
        .btn-get-started {
            display: inline-flex; align-items: center; padding: 10px 24px;
            background: #06d6a0; color: #0f172a; font-size: 14px; font-weight: 600;
            border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-get-started:hover { background: #05c493; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(6,214,160,0.3); }
        .nav-mobile-toggle { display: none; background: none; border: none; cursor: pointer; padding: 8px; }
        .nav-mobile-toggle svg { width: 24px; height: 24px; color: #1e293b; }

        /* ===== HERO ===== */
        .hero {
            position: relative;
            background: linear-gradient(135deg, #1a4d80 0%, #163d66 40%, #0f172a 100%);
            color: #fff; padding: 160px 0 100px; overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .hero::after {
            content: '';
            position: absolute; top: -50%; right: -20%;
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(6,214,160,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero .container { position: relative; z-index: 2; text-align: center; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 16px; background: rgba(6,214,160,0.15);
            border: 1px solid rgba(6,214,160,0.3); border-radius: 50px;
            font-size: 13px; font-weight: 500; color: #06d6a0; margin-bottom: 24px;
        }
        .hero h1 {
            font-size: 56px; font-weight: 800; letter-spacing: -0.03em;
            line-height: 1.1; margin-bottom: 20px;
            max-width: 720px; margin-left: auto; margin-right: auto;
        }
        .hero h1 .highlight {
            background: linear-gradient(135deg, #06d6a0, #34d399);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-sub { font-size: 20px; font-weight: 400; color: rgba(255,255,255,0.7); max-width: 560px; margin: 0 auto 36px; line-height: 1.6; }
        .hero-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-bottom: 12px; }
        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 16px 32px; background: #06d6a0; color: #0f172a;
            font-size: 16px; font-weight: 600; border-radius: 8px;
            border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-hero-primary:hover { background: #05c493; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(6,214,160,0.3); }
        .btn-hero-outline {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 16px 32px; background: transparent; color: #fff;
            font-size: 16px; font-weight: 600; border-radius: 8px;
            border: 2px solid rgba(255,255,255,0.3); cursor: pointer; transition: all 0.2s;
        }
        .btn-hero-outline:hover { border-color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.05); }
        .hero-price-note { font-size: 14px; color: rgba(255,255,255,0.5); margin-bottom: 48px; }

        /* Domain Search */
        .domain-search-wrap { max-width: 640px; margin: 0 auto 48px; }
        .domain-search-box {
            display: flex; align-items: center;
            background: #fff; border-radius: 50px;
            padding: 6px 6px 6px 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        .domain-search-box input {
            flex: 1; border: none; outline: none;
            font-size: 16px; font-family: inherit; color: #1e293b;
            background: transparent; min-width: 0;
        }
        .domain-search-box input::placeholder { color: #94a3b8; }
        .domain-tld-select {
            border: none; outline: none; font-size: 15px; font-family: inherit;
            color: #64748b; background: transparent; padding: 0 8px;
            cursor: pointer; font-weight: 500; -webkit-appearance: auto; appearance: auto;
        }
        .btn-domain-search {
            padding: 14px 28px; background: #06d6a0; color: #0f172a;
            font-size: 15px; font-weight: 600; border: none; border-radius: 50px;
            cursor: pointer; transition: all 0.2s; white-space: nowrap; font-family: inherit;
        }
        .btn-domain-search:hover { background: #05c493; }

        /* Trust Badges */
        .trust-badges { display: flex; justify-content: center; gap: 32px; flex-wrap: wrap; }
        .trust-badge { display: flex; align-items: center; gap: 8px; font-size: 14px; color: rgba(255,255,255,0.6); font-weight: 500; }
        .trust-badge svg { width: 20px; height: 20px; color: #06d6a0; flex-shrink: 0; }

        /* ===== BRANDS BAR ===== */
        .brands-section { padding: 48px 0; border-bottom: 1px solid #f1f5f9; }
        .brands-section p {
            text-align: center; font-size: 14px; font-weight: 600;
            color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 28px;
        }
        .brands-row { display: flex; justify-content: center; align-items: center; gap: 48px; flex-wrap: wrap; }
        .brand-item { font-size: 20px; font-weight: 700; color: #cbd5e1; letter-spacing: -0.5px; transition: color 0.2s; }
        .brand-item:hover { color: #94a3b8; }

        /* ===== FEATURES SECTION ===== */
        .features-section { padding: 96px 0; background: #fff; }
        .section-header { text-align: center; margin-bottom: 64px; }
        .section-header h2 { font-size: 36px; font-weight: 700; letter-spacing: -0.02em; color: #1e293b; margin-bottom: 12px; }
        .section-header h2 .accent { color: #1a4d80; }
        .section-header p { font-size: 18px; color: #64748b; max-width: 540px; margin: 0 auto; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .feature-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 32px 28px; transition: all 0.3s ease;
        }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); border-color: transparent; }
        .feature-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
        }
        .feature-icon svg { width: 24px; height: 24px; }
        .feature-icon.blue { background: #eff6ff; color: #2563eb; }
        .feature-icon.green { background: #ecfdf5; color: #059669; }
        .feature-icon.purple { background: #f5f3ff; color: #7c3aed; }
        .feature-icon.orange { background: #fff7ed; color: #ea580c; }
        .feature-icon.rose { background: #fff1f2; color: #e11d48; }
        .feature-icon.cyan { background: #ecfeff; color: #0891b2; }
        .feature-card h3 { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: #64748b; line-height: 1.7; }

        /* ===== PRICING SECTION ===== */
        .pricing-section { padding: 96px 0; background: #f8fafc; }
        .billing-toggle { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 48px; }
        .billing-toggle span { font-size: 15px; font-weight: 500; color: #64748b; cursor: pointer; transition: color 0.2s; }
        .billing-toggle span.active { color: #1e293b; font-weight: 600; }
        .toggle-switch {
            position: relative; width: 52px; height: 28px;
            background: #cbd5e1; border-radius: 50px; cursor: pointer; transition: background 0.3s;
        }
        .toggle-switch.active { background: #06d6a0; }
        .toggle-switch::after {
            content: ''; position: absolute; top: 3px; left: 3px;
            width: 22px; height: 22px; background: #fff; border-radius: 50%;
            transition: transform 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .toggle-switch.active::after { transform: translateX(24px); }
        .save-badge {
            display: inline-block; padding: 2px 10px;
            background: #06d6a0; color: #0f172a; font-size: 12px; font-weight: 700; border-radius: 50px;
        }
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1080px; margin: 0 auto; align-items: start; }
        .pricing-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 36px 32px; position: relative; transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
        }
        .pricing-card:hover { box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .pricing-card.featured {
            border: 2px solid #06d6a0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transform: scale(1.03);
        }
        .pricing-card.featured:hover { box-shadow: 0 25px 50px rgba(0,0,0,0.12); }
        .popular-badge {
            position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
            padding: 4px 20px; background: #06d6a0; color: #0f172a;
            font-size: 12px; font-weight: 700; border-radius: 50px;
            text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
        }
        .pricing-card .plan-name { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .pricing-card .plan-desc { font-size: 14px; color: #94a3b8; margin-bottom: 24px; }
        .plan-price-wrap { margin-bottom: 24px; }
        .plan-price-wrap .currency { font-size: 24px; font-weight: 700; color: #1e293b; vertical-align: top; line-height: 1.4; }
        .plan-price-wrap .amount { font-size: 48px; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; line-height: 1; }
        .plan-price-wrap .period { font-size: 16px; color: #94a3b8; font-weight: 400; }
        .plan-features-list { list-style: none; margin-bottom: 28px; }
        .plan-features-list li { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 14px; color: #475569; }
        .plan-features-list li svg { width: 18px; height: 18px; color: #06d6a0; flex-shrink: 0; }
        .btn-plan {
            display: block; width: 100%; padding: 14px; text-align: center;
            font-size: 15px; font-weight: 600; border-radius: 8px;
            border: none; cursor: pointer; transition: all 0.2s; font-family: inherit;
        }
        .btn-plan-primary { background: #06d6a0; color: #0f172a; }
        .btn-plan-primary:hover { background: #05c493; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(6,214,160,0.3); }
        .btn-plan-outline { background: #fff; color: #1a4d80; border: 2px solid #e2e8f0; }
        .btn-plan-outline:hover { border-color: #1a4d80; background: #f8fafc; }
        .pricing-guarantee {
            text-align: center; margin-top: 40px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-size: 14px; color: #64748b;
        }
        .pricing-guarantee svg { width: 20px; height: 20px; color: #06d6a0; }

        /* ===== STATS SECTION ===== */
        .stats-section { padding: 80px 0; background: #0f172a; color: #fff; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px; text-align: center; }
        .stat-item .stat-number {
            font-size: 48px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; margin-bottom: 8px;
            background: linear-gradient(135deg, #06d6a0, #34d399);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .stat-item .stat-label { font-size: 15px; color: rgba(255,255,255,0.5); font-weight: 500; }

        /* ===== HOW IT WORKS ===== */
        .how-section { padding: 96px 0; background: #fff; }
        .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 48px; max-width: 960px; margin: 0 auto; }
        .step-item { text-align: center; }
        .step-number {
            width: 64px; height: 64px; border-radius: 50%;
            background: #06d6a0; color: #0f172a;
            font-size: 24px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .step-item h3 { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .step-item p { font-size: 15px; color: #64748b; max-width: 280px; margin: 0 auto; }

        /* ===== CTA BANNER ===== */
        .cta-section {
            padding: 96px 0;
            background: linear-gradient(135deg, #1a4d80 0%, #0f172a 100%);
            color: #fff; text-align: center; position: relative; overflow: hidden;
        }
        .cta-section::before {
            content: ''; position: absolute; top: -200px; right: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(6,214,160,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .cta-section::after {
            content: ''; position: absolute; bottom: -200px; left: -200px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(37,99,235,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .cta-section .container { position: relative; z-index: 2; }
        .cta-section h2 { font-size: 40px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 12px; }
        .cta-sub { font-size: 18px; color: rgba(255,255,255,0.6); margin-bottom: 36px; }
        .cta-small { font-size: 14px; color: rgba(255,255,255,0.4); margin-top: 16px; }

        /* ===== FOOTER ===== */
        .footer { background: #0f172a; color: rgba(255,255,255,0.6); padding: 64px 0 0; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; margin-bottom: 48px; }
        .footer-brand-col .footer-logo { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 12px; }
        .footer-brand-col .footer-tagline { font-size: 14px; line-height: 1.7; max-width: 280px; }
        .footer-col h4 {
            font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px;
        }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 10px; }
        .footer-col ul li a { font-size: 14px; color: rgba(255,255,255,0.5); transition: color 0.2s; }
        .footer-col ul li a:hover { color: #06d6a0; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.08); padding: 24px 0;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 16px;
        }
        .footer-bottom p { font-size: 13px; color: rgba(255,255,255,0.3); }
        .footer-socials { display: flex; gap: 16px; }
        .footer-socials a {
            width: 36px; height: 36px; border-radius: 8px;
            background: rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .footer-socials a:hover { background: rgba(6,214,160,0.15); }
        .footer-socials a svg { width: 16px; height: 16px; color: rgba(255,255,255,0.4); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .hero h1 { font-size: 44px; }
            .pricing-grid { grid-template-columns: repeat(2, 1fr); }
            .pricing-card.featured { transform: scale(1); }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .nav-mobile-toggle { display: block; }
            .nav-right .nav-login { display: none; }
            .hero { padding: 120px 0 64px; }
            .hero h1 { font-size: 36px; }
            .hero-sub { font-size: 17px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .pricing-grid { grid-template-columns: 1fr; max-width: 420px; margin: 0 auto; }
            .pricing-card.featured { transform: scale(1); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 24px; }
            .steps-grid { grid-template-columns: 1fr; gap: 36px; }
            .footer-grid { grid-template-columns: repeat(2, 1fr); gap: 32px; }
            .section-header h2 { font-size: 28px; }
            .trust-badges { gap: 16px; }
            .trust-badge { font-size: 12px; }
            .brands-row { gap: 24px; }
            .cta-section h2 { font-size: 30px; }
        }
        @media (max-width: 480px) {
            .hero h1 { font-size: 30px; }
            .features-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-item .stat-number { font-size: 36px; }
            .footer-grid { grid-template-columns: 1fr; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .domain-search-box {
                flex-wrap: wrap; border-radius: 16px; padding: 8px; gap: 8px;
            }
            .domain-search-box input { min-width: 100%; padding: 12px 16px; }
            .btn-domain-search { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar" id="navbar">
    <div class="container">
        <a href="/" class="nav-brand">PNLCS</a>
        <div class="nav-links" id="navLinks">
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="{{ route('client.store') }}">Products</a>
            <a href="{{ route('client.contact') }}">Contact</a>
            <a href="/client/domain-pricing">Domains</a>
        </div>
        <div class="nav-right">
            <a href="{{ route('client.login') }}" class="nav-login">Login</a>
            <a href="{{ route('client.store') }}" class="btn-get-started">Get Started</a>
            <button class="nav-mobile-toggle" id="mobileToggle" aria-label="Menu">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section class="hero">
    <div class="container">
        <div class="hero-badge">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>
            Trusted by 10,000+ websites worldwide
        </div>
        <h1><span class="highlight">Blazing Fast</span> Web Hosting</h1>
        <p class="hero-sub">Enterprise infrastructure for your websites. 99.9% uptime guarantee, free SSL certificates, and 24/7 expert support.</p>
        <div class="hero-buttons">
            <a href="{{ route('client.store') }}" class="btn-hero-primary">
                Start Free Trial
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="#pricing" class="btn-hero-outline">View Plans</a>
        </div>
        <p class="hero-price-note">Starting at just $4.99/mo</p>

        <div class="domain-search-wrap">
            <form action="/client/domain-search" method="GET" class="domain-search-box">
                <input type="text" name="domain" placeholder="Find your perfect domain name..." required>
                <select name="tld" class="domain-tld-select">
                    <option value=".com">.com</option>
                    <option value=".net">.net</option>
                    <option value=".org">.org</option>
                    <option value=".io">.io</option>
                    <option value=".co">.co</option>
                    <option value=".dev">.dev</option>
                    <option value=".ai">.ai</option>
                    <option value=".app">.app</option>
                    <option value=".xyz">.xyz</option>
                    <option value=".store">.store</option>
                </select>
                <button type="submit" class="btn-domain-search">Search</button>
            </form>
        </div>

        <div class="trust-badges">
            <div class="trust-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                30-Day Money Back
            </div>
            <div class="trust-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Free SSL Certificate
            </div>
            <div class="trust-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                99.9% Uptime SLA
            </div>
            <div class="trust-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                24/7 Support
            </div>
        </div>
    </div>
</section>

<!-- ===== BRANDS BAR ===== -->
<section class="brands-section">
    <div class="container">
        <p>Trusted by 10,000+ websites worldwide</p>
        <div class="brands-row">
            <span class="brand-item">WordPress</span>
            <span class="brand-item">WooCommerce</span>
            <span class="brand-item">Laravel</span>
            <span class="brand-item">Joomla</span>
            <span class="brand-item">Drupal</span>
            <span class="brand-item">Magento</span>
        </div>
    </div>
</section>

<!-- ===== FEATURES ===== -->
<section class="features-section" id="features">
    <div class="container">
        <div class="section-header">
            <h2>Everything You Need to <span class="accent">Succeed Online</span></h2>
            <p>Industry-leading infrastructure and tools designed to make your websites faster, more secure, and easier to manage.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3>Lightning Speed</h3>
                <p>NVMe SSD storage with LiteSpeed web server delivers up to 20x faster load times than traditional hosting.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h3>Free SSL Certificates</h3>
                <p>Every domain gets a free Let's Encrypt SSL certificate, automatically installed and renewed to keep your site secure.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon purple">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <h3>One-Click Installer</h3>
                <p>Install WordPress, Joomla, Magento, and 400+ other applications instantly with our automatic installer.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon orange">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
                <h3>Daily Backups</h3>
                <p>Automated daily backups with 30-day retention. Restore your entire site or individual files with a single click.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon rose">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3>DDoS Protection</h3>
                <p>Enterprise-grade DDoS mitigation and Web Application Firewall protect your site from malicious attacks 24/7.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon cyan">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3>Global CDN</h3>
                <p>Content delivery network with 200+ edge locations worldwide ensures blazing-fast load times for visitors everywhere.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== PRICING ===== -->
<section class="pricing-section" id="pricing">
    <div class="container">
        <div class="section-header">
            <h2>Simple, Transparent <span class="accent">Pricing</span></h2>
            <p>No hidden fees. No surprises. Cancel anytime.</p>
        </div>

        <div class="billing-toggle">
            <span id="monthlyLabel" class="active">Monthly</span>
            <div class="toggle-switch" id="billingToggle"></div>
            <span id="annuallyLabel">Annually</span>
            <span class="save-badge">Save 20%</span>
        </div>

        @php
            $sharedPlans = $products->filter(function($p) {
                return $p->group && stripos($p->group->name, 'shared') !== false;
            })->values()->take(3);

            $featureMap = [
                0 => ['10GB NVMe SSD Storage', '100GB Bandwidth', '1 Website', '5 Email Accounts', 'Free SSL Certificate', 'Daily Backups', '24/7 Support'],
                1 => ['50GB NVMe SSD Storage', '500GB Bandwidth', '5 Websites', '25 Email Accounts', 'Free SSL Certificate', 'Daily Backups', 'Staging Environment', 'Priority Support'],
                2 => ['100GB NVMe SSD Storage', 'Unlimited Bandwidth', 'Unlimited Websites', 'Unlimited Email Accounts', 'Free SSL Certificate', 'Daily Backups', 'Staging Environment', 'Dedicated Resources', 'Priority Support'],
            ];

            $planDescs = [
                0 => 'Perfect for personal sites and blogs',
                1 => 'Ideal for growing businesses',
                2 => 'For high-traffic sites and agencies',
            ];
        @endphp

        <div class="pricing-grid">
            @forelse($sharedPlans as $idx => $product)
                @php
                    $pricing = $product->pricing->first();
                    $monthlyPrice = $pricing ? (float)($pricing->monthly ?? 0) : 0;
                    $annuallyTotal = $pricing ? (float)($pricing->annually ?? 0) : 0;
                    $annuallyMonthly = $annuallyTotal > 0 ? round($annuallyTotal / 12, 2) : 0;
                    $isFeatured = ($idx === 1);
                    $features = $featureMap[$idx] ?? $featureMap[0];
                    $planDesc = $planDescs[$idx] ?? '';
                @endphp
                <div class="pricing-card {{ $isFeatured ? 'featured' : '' }}">
                    @if($isFeatured)
                        <div class="popular-badge">Most Popular</div>
                    @endif
                    <div class="plan-name">{{ $product->name }}</div>
                    <div class="plan-desc">{{ $planDesc }}</div>
                    <div class="plan-price-wrap">
                        @if($monthlyPrice > 0)
                            <span class="currency">$</span><span class="amount monthly-price" data-monthly="{{ number_format($monthlyPrice, 2) }}" data-annually="{{ number_format($annuallyMonthly, 2) }}">{{ number_format($monthlyPrice, 2) }}</span><span class="period">/mo</span>
                        @else
                            <span class="amount" style="font-size:28px;">Contact Us</span>
                        @endif
                    </div>
                    <ul class="plan-features-list">
                        @foreach($features as $feature)
                            <li>
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('client.store.configure', $product->slug) }}" class="btn-plan {{ $isFeatured ? 'btn-plan-primary' : 'btn-plan-outline' }}">
                        Get Started
                    </a>
                </div>
            @empty
                <div style="grid-column:1/-1; text-align:center; color:#94a3b8; padding:48px;">
                    <p>No plans available at this time. Please check back soon.</p>
                </div>
            @endforelse
        </div>

        <div class="pricing-guarantee">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            30-Day Money-Back Guarantee on all plans
        </div>
    </div>
</section>

<!-- ===== STATS ===== -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">10,000+</div>
                <div class="stat-label">Websites Hosted</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">99.9%</div>
                <div class="stat-label">Uptime Guarantee</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Expert Support</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">&lt;30s</div>
                <div class="stat-label">Avg Response Time</div>
            </div>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section class="how-section">
    <div class="container">
        <div class="section-header">
            <h2>Get Online in <span class="accent">3 Simple Steps</span></h2>
            <p>From sign-up to launch in under 5 minutes. No technical knowledge required.</p>
        </div>
        <div class="steps-grid">
            <div class="step-item">
                <div class="step-number">1</div>
                <h3>Choose a Plan</h3>
                <p>Select the hosting plan that fits your needs. Upgrade or downgrade anytime with no penalties.</p>
            </div>
            <div class="step-item">
                <div class="step-number">2</div>
                <h3>Register Your Domain</h3>
                <p>Pick a new domain name or connect your existing one. Free domain included with annual plans.</p>
            </div>
            <div class="step-item">
                <div class="step-number">3</div>
                <h3>Launch Your Site</h3>
                <p>Upload your site or use our one-click installer to deploy WordPress, Laravel, and 400+ other apps.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="cta-section">
    <div class="container">
        <h2>Ready to Get Started?</h2>
        <p class="cta-sub">Join thousands of satisfied customers and take your website to the next level.</p>
        <a href="{{ route('client.store') }}" class="btn-hero-primary" style="font-size:18px; padding:18px 40px;">
            Start Your Free Trial
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
        <p class="cta-small">No credit card required</p>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand-col">
                <div class="footer-logo">PNLCS</div>
                <p class="footer-tagline">Premium hosting solutions for businesses and individuals worldwide. Fast, reliable, and backed by 24/7 expert support.</p>
            </div>
            <div class="footer-col">
                <h4>Products</h4>
                <ul>
                    <li><a href="{{ route('client.store') }}">Shared Hosting</a></li>
                    <li><a href="{{ route('client.store') }}">VPS Hosting</a></li>
                    <li><a href="{{ route('client.store') }}">Dedicated Servers</a></li>
                    <li><a href="{{ route('client.store') }}">Domains</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="{{ route('client.login') }}">Client Area</a></li>
                    <li><a href="{{ route('client.tickets.index') }}">Support</a></li>
                    <li><a href="{{ route('client.invoices.index') }}">Billing</a></li>
                    <li><a href="{{ route('client.contact') }}">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="{{ route('client.kb.index') }}">Knowledge Base</a></li>
                    <li><a href="{{ route('client.announcements.index') }}">Announcements</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; {{ date('Y') }} PNLCS. All rights reserved.</p>
            <div class="footer-socials">
                <a href="#" aria-label="Twitter">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="#" aria-label="GitHub">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                </a>
                <a href="#" aria-label="LinkedIn">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    // Navbar scroll effect
    var navbar = document.getElementById('navbar');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                var offset = 80;
                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });

    // Billing toggle
    var toggle = document.getElementById('billingToggle');
    var monthlyLabel = document.getElementById('monthlyLabel');
    var annuallyLabel = document.getElementById('annuallyLabel');
    var isAnnual = false;

    if (toggle) {
        toggle.addEventListener('click', function() {
            isAnnual = !isAnnual;
            toggle.classList.toggle('active');
            monthlyLabel.classList.toggle('active');
            annuallyLabel.classList.toggle('active');

            document.querySelectorAll('.monthly-price').forEach(function(el) {
                if (isAnnual) {
                    el.textContent = el.getAttribute('data-annually');
                } else {
                    el.textContent = el.getAttribute('data-monthly');
                }
            });
        });
    }

    // Mobile menu toggle
    var mobileToggle = document.getElementById('mobileToggle');
    var navLinks = document.getElementById('navLinks');
    var menuOpen = false;

    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function() {
            menuOpen = !menuOpen;
            if (menuOpen) {
                navLinks.style.display = 'flex';
                navLinks.style.flexDirection = 'column';
                navLinks.style.position = 'absolute';
                navLinks.style.top = '72px';
                navLinks.style.left = '0';
                navLinks.style.right = '0';
                navLinks.style.background = '#fff';
                navLinks.style.padding = '16px 24px';
                navLinks.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                navLinks.style.gap = '16px';
                navLinks.style.borderBottom = '1px solid #e2e8f0';
            } else {
                navLinks.style.display = '';
                navLinks.style.flexDirection = '';
                navLinks.style.position = '';
                navLinks.style.top = '';
                navLinks.style.left = '';
                navLinks.style.right = '';
                navLinks.style.background = '';
                navLinks.style.padding = '';
                navLinks.style.boxShadow = '';
                navLinks.style.gap = '';
                navLinks.style.borderBottom = '';
            }
        });
    }
})();
</script>

</body>
</html>
