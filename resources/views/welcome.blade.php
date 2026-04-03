<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PNLCS — Professional Web Hosting, Domains & Servers</title>
    <meta name="description" content="PNLCS by Panelica — reliable web hosting, VPS servers, domains, and SSL. Built on Panelica's isolated infrastructure with Cgroups v2 and Nginx.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    <style>
        /* ===== RESET & BASE ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--theme-text-color, #1e293b);
            background: var(--theme-body-bg, #f7f9fc);
            line-height: 1.6; font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { text-decoration: none; color: inherit; transition: color 0.2s; }
        ul { list-style: none; }
        img { max-width: 100%; }
        .container { max-width: 1240px; margin: 0 auto; padding: 0 24px; }
        .section-title { font-size: 36px; font-weight: 800; text-align: center; margin-bottom: 12px; letter-spacing: -0.5px; }
        .section-subtitle { text-align: center; color: var(--theme-muted-color, #64748b); font-size: 17px; margin-bottom: 48px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px; background: var(--theme-primary, #405189); color: #fff;
            border-radius: 10px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;
            transition: all 0.25s; box-shadow: 0 4px 15px rgba(64,81,137,0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(64,81,137,0.4); filter: brightness(1.1); }
        .btn-accent {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px; background: var(--theme-welcome-accent, #10b981); color: #fff;
            border-radius: 10px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;
            transition: all 0.25s; box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        .btn-accent:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); filter: brightness(1.1); }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 26px; background: transparent; color: var(--theme-primary, #405189);
            border: 2px solid var(--theme-primary, #405189); border-radius: 10px;
            font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.25s;
        }
        .btn-outline:hover { background: var(--theme-primary, #405189); color: #fff; }

        /* ===== 2. TOP BAR ===== */
        .top-bar {
            background: var(--theme-nav-bg, #0f1117); color: rgba(255,255,255,0.7);
            padding: 8px 0; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .top-bar__inner { display: flex; align-items: center; justify-content: space-between; }
        .top-bar__left, .top-bar__right { display: flex; align-items: center; gap: 20px; }
        .top-bar__item { display: flex; align-items: center; gap: 6px; transition: color 0.2s; cursor: pointer; }
        .top-bar__item:hover { color: #fff; }
        .top-bar__item i { font-size: 14px; }
        .top-bar__divider { width: 1px; height: 14px; background: rgba(255,255,255,0.15); }

        /* ===== 3. MAIN NAVIGATION ===== */
        .main-nav {
            background: var(--theme-nav-bg, #0f1117); padding: 0;
            position: sticky; top: 0; z-index: 150;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: box-shadow 0.3s;
        }
        .main-nav.scrolled { box-shadow: 0 4px 30px rgba(0,0,0,0.3); }
        .main-nav__inner { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .main-nav__brand { font-size: 24px; font-weight: 900; color: #fff; display: flex; align-items: center; gap: 4px; }
        .main-nav__brand img { height: 36px; }
        .main-nav__brand-dot { width: 8px; height: 8px; background: var(--theme-welcome-accent, #10b981); border-radius: 50%; display: inline-block; }
        .main-nav__menu { display: flex; align-items: center; gap: 4px; }
        .main-nav__item { position: relative; }
        .main-nav__link {
            display: flex; align-items: center; gap: 4px; padding: 20px 14px;
            color: rgba(255,255,255,0.7); font-size: 14px; font-weight: 600;
            transition: color 0.2s; cursor: pointer; white-space: nowrap;
        }
        .main-nav__link:hover, .main-nav__item:hover > .main-nav__link { color: #fff; }
        .main-nav__link i.ri-arrow-down-s-line { font-size: 12px; opacity: 0.6; }
        .main-nav__dropdown {
            position: absolute; top: 100%; left: 0; min-width: 220px;
            background: var(--theme-card-bg, #fff); border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15); padding: 8px;
            opacity: 0; visibility: hidden; transform: translateY(8px);
            transition: all 0.25s; z-index: 160;
        }
        .main-nav__item:hover > .main-nav__dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .main-nav__dropdown-link {
            display: flex; align-items: center; gap: 10px; padding: 10px 14px;
            border-radius: 8px; font-size: 14px; font-weight: 500;
            color: var(--theme-text-color, #1e293b); transition: all 0.15s;
        }
        .main-nav__dropdown-link:hover { background: var(--theme-body-bg, #f7f9fc); color: var(--theme-primary, #405189); }
        .main-nav__dropdown-link i { font-size: 18px; color: var(--theme-primary, #405189); width: 24px; text-align: center; }
        .main-nav__dropdown-badge {
            font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
            background: var(--theme-welcome-accent, #10b981); color: #fff; margin-left: auto;
        }
        .main-nav__dropdown-badge--soon { background: var(--theme-muted-color, #94a3b8); }
        /* Mega menu */
        .main-nav__mega {
            position: absolute; top: 100%; left: 50%; transform: translateX(-50%) translateY(8px);
            min-width: 680px; background: var(--theme-card-bg, #fff); border-radius: 16px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.18); padding: 24px;
            opacity: 0; visibility: hidden; transition: all 0.25s; z-index: 160;
        }
        .main-nav__item:hover > .main-nav__mega { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        .main-nav__mega-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .main-nav__mega-col-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--theme-muted-color, #94a3b8); padding: 6px 10px; margin-bottom: 4px; }
        .main-nav__mega-promo {
            grid-column: 1 / -1; margin-top: 16px; padding: 16px 20px;
            background: linear-gradient(135deg, var(--theme-primary, #405189), var(--theme-accent, #6366f1));
            border-radius: 12px; color: #fff; display: flex; align-items: center; justify-content: space-between;
        }
        .main-nav__mega-promo-text { font-weight: 700; font-size: 14px; }
        .main-nav__mega-promo-btn { padding: 8px 16px; background: #fff; color: var(--theme-primary, #405189); border-radius: 8px; font-weight: 700; font-size: 13px; }
        .main-nav__right { display: flex; align-items: center; gap: 12px; }
        .main-nav__cart { position: relative; color: rgba(255,255,255,0.7); font-size: 20px; padding: 8px; transition: color 0.2s; }
        .main-nav__cart:hover { color: #fff; }
        .main-nav__login { padding: 8px 20px; border: 2px solid rgba(255,255,255,0.2); color: #fff; border-radius: 8px; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .main-nav__login:hover { border-color: var(--theme-welcome-accent, #10b981); background: var(--theme-welcome-accent, #10b981); }
        .main-nav__hamburger { display: none; background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; padding: 8px; }
        .main-nav__mobile-menu { display: none; }

        /* ===== 4. HERO ===== */
        .hero {
            position: relative; overflow: hidden; padding: 100px 0 80px;
            background: linear-gradient(135deg, var(--theme-hero-bg-start, #0c1222) 0%, var(--theme-hero-bg-mid, #162447) 50%, var(--theme-hero-bg-end, #1a1a3e) 100%);
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px; pointer-events: none;
        }
        .hero__orb {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.3; pointer-events: none;
        }
        .hero__orb--1 { width: 400px; height: 400px; background: var(--theme-primary, #405189); top: -100px; right: -100px; animation: orbFloat1 8s ease-in-out infinite; }
        .hero__orb--2 { width: 300px; height: 300px; background: var(--theme-accent, #6366f1); bottom: -50px; left: -50px; animation: orbFloat2 10s ease-in-out infinite; }
        .hero__orb--3 { width: 200px; height: 200px; background: var(--theme-welcome-accent, #10b981); top: 50%; left: 50%; animation: orbFloat3 12s ease-in-out infinite; }
        @keyframes orbFloat1 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-30px, 30px); } }
        @keyframes orbFloat2 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(20px, -40px); } }
        @keyframes orbFloat3 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-40px, 20px); } }
        .hero__inner { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; position: relative; z-index: 2; }
        .hero__badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px;
            background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3);
            border-radius: 50px; color: var(--theme-welcome-accent, #10b981);
            font-size: 13px; font-weight: 600; margin-bottom: 20px;
        }
        .hero__badge i { font-size: 14px; }
        .hero__title { font-size: 52px; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 8px; letter-spacing: -1px; }
        .hero__title span { color: var(--theme-welcome-accent, #10b981); }
        .hero__title-sub { font-size: 28px; font-weight: 700; color: rgba(255,255,255,0.7); margin-bottom: 20px; }
        .hero__desc { font-size: 17px; color: rgba(255,255,255,0.55); line-height: 1.7; margin-bottom: 28px; max-width: 480px; }
        .hero__stats { display: flex; gap: 16px; margin-bottom: 32px; }
        .hero__stat {
            display: flex; align-items: center; gap: 8px; padding: 10px 18px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; color: #fff; font-size: 14px; font-weight: 600;
        }
        .hero__stat i { color: var(--theme-welcome-accent, #10b981); font-size: 18px; }
        .hero__visual {
            position: relative; display: flex; align-items: center; justify-content: center; min-height: 380px;
        }
        .hero__visual-box {
            width: 320px; height: 320px; border-radius: 24px; position: relative;
            background: linear-gradient(135deg, rgba(64,81,137,0.3), rgba(99,102,241,0.2));
            border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(20px);
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
        }
        .hero__visual-icon { font-size: 64px; color: var(--theme-welcome-accent, #10b981); }
        .hero__visual-label { color: rgba(255,255,255,0.6); font-size: 14px; font-weight: 600; }
        .hero__visual-bars { display: flex; gap: 6px; align-items: flex-end; height: 60px; }
        .hero__visual-bar { width: 16px; border-radius: 4px 4px 0 0; background: var(--theme-primary, #405189); animation: barGrow 2s ease-in-out infinite; }
        .hero__visual-bar:nth-child(1) { height: 30px; animation-delay: 0s; }
        .hero__visual-bar:nth-child(2) { height: 45px; animation-delay: 0.2s; background: var(--theme-accent, #6366f1); }
        .hero__visual-bar:nth-child(3) { height: 60px; animation-delay: 0.4s; background: var(--theme-welcome-accent, #10b981); }
        .hero__visual-bar:nth-child(4) { height: 38px; animation-delay: 0.6s; }
        .hero__visual-bar:nth-child(5) { height: 52px; animation-delay: 0.8s; background: var(--theme-accent, #6366f1); }
        @keyframes barGrow { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
        @keyframes float-hero { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-16px); } }
        .hero__callout {
            position: absolute; padding: 10px 18px; background: var(--theme-card-bg, #fff);
            border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            font-size: 13px; font-weight: 700; color: var(--theme-text-color, #1e293b);
            display: flex; align-items: center; gap: 8px; white-space: nowrap;
        }
        .hero__callout i { font-size: 18px; }
        .hero__callout--1 { top: 20px; right: -20px; }
        .hero__callout--1 i { color: var(--theme-welcome-accent, #10b981); }
        .hero__callout--2 { bottom: 30px; left: -30px; }
        .hero__callout--2 i { color: var(--theme-accent, #6366f1); }

        /* ===== 5. DOMAIN SEARCH ===== */
        .domain-search { padding: 60px 0 40px; background: var(--theme-body-bg, #f7f9fc); }
        .domain-search__bar {
            display: flex; max-width: 680px; margin: 0 auto 36px;
            background: var(--theme-card-bg, #fff); border-radius: 14px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08); overflow: hidden;
            border: 2px solid var(--theme-border-color, #e2e8f0);
        }
        .domain-search__bar:focus-within { border-color: var(--theme-primary, #405189); box-shadow: 0 8px 40px rgba(64,81,137,0.15); }
        .domain-search__icon { display: flex; align-items: center; padding: 0 16px; color: var(--theme-muted-color, #94a3b8); font-size: 20px; }
        .domain-search__input {
            flex: 1; border: none; outline: none; padding: 16px 0; font-size: 16px;
            font-family: inherit; background: transparent; color: var(--theme-text-color, #1e293b);
        }
        .domain-search__input::placeholder { color: var(--theme-muted-color, #94a3b8); }
        .domain-search__btn {
            padding: 16px 32px; background: var(--theme-primary, #405189); color: #fff;
            border: none; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: filter 0.2s; font-family: inherit;
        }
        .domain-search__btn:hover { filter: brightness(1.1); }
        .domain-search__extensions {
            display: flex; gap: 16px; overflow-x: auto; padding-bottom: 8px;
            scrollbar-width: thin; scrollbar-color: var(--theme-border-color, #e2e8f0) transparent;
        }
        .domain-search__ext {
            flex: 0 0 auto; padding: 16px 24px; background: var(--theme-card-bg, #fff);
            border-radius: 12px; border: 1px solid var(--theme-border-color, #e2e8f0);
            text-align: center; min-width: 120px; transition: all 0.25s; cursor: pointer;
        }
        .domain-search__ext:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); border-color: var(--theme-primary, #405189); }
        .domain-search__ext-name { font-size: 20px; font-weight: 800; color: var(--theme-text-color, #1e293b); margin-bottom: 4px; }
        .domain-search__ext-price { font-size: 14px; color: var(--theme-muted-color, #94a3b8); font-weight: 600; margin-bottom: 8px; }
        .domain-search__ext-link { font-size: 12px; font-weight: 700; color: var(--theme-primary, #405189); }

        /* ===== 6. PROMO CARDS ===== */
        .promo-cards { padding: 40px 0 60px; }
        .promo-cards__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .promo-card {
            padding: 28px 24px; border-radius: 16px; color: #fff; position: relative; overflow: hidden;
            transition: transform 0.3s; cursor: pointer;
        }
        .promo-card:hover { transform: translateY(-6px); }
        .promo-card::after {
            content: ''; position: absolute; top: -30px; right: -30px;
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .promo-card--1 { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .promo-card--2 { background: linear-gradient(135deg, var(--theme-primary, #405189), var(--theme-accent, #6366f1)); }
        .promo-card--3 { background: linear-gradient(135deg, #8b5cf6, #a855f7); }
        .promo-card--4 { background: linear-gradient(135deg, #1e293b, #334155); }
        .promo-card__title { font-size: 18px; font-weight: 800; margin-bottom: 4px; }
        .promo-card__subtitle { font-size: 13px; opacity: 0.8; margin-bottom: 16px; }
        .promo-card__prices { display: flex; align-items: baseline; gap: 8px; margin-bottom: 16px; }
        .promo-card__old { font-size: 14px; text-decoration: line-through; opacity: 0.6; }
        .promo-card__new { font-size: 28px; font-weight: 900; }
        .promo-card__new small { font-size: 14px; font-weight: 600; }
        .promo-card__cta { padding: 8px 20px; background: rgba(255,255,255,0.2); border-radius: 8px; font-size: 13px; font-weight: 700; display: inline-block; transition: background 0.2s; }
        .promo-card__cta:hover { background: rgba(255,255,255,0.35); }

        /* ===== 7. HOSTING PLANS ===== */
        .hosting-plans { padding: 80px 0; background: var(--theme-body-bg, #f7f9fc); }
        .hosting-plans__grid { display: grid; grid-template-columns: 280px 1fr 1fr 1fr; gap: 20px; align-items: stretch; }
        .hosting-plans__promo {
            background: linear-gradient(135deg, var(--theme-primary, #405189), var(--theme-accent, #6366f1));
            border-radius: 16px; padding: 32px 24px; color: #fff; display: flex; flex-direction: column; justify-content: center;
        }
        .hosting-plans__promo-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.9; }
        .hosting-plans__promo h3 { font-size: 22px; font-weight: 800; margin-bottom: 12px; line-height: 1.3; }
        .hosting-plans__promo p { font-size: 14px; opacity: 0.8; margin-bottom: 24px; line-height: 1.6; }
        .hosting-plans__promo-btn {
            padding: 10px 22px; background: #fff; color: var(--theme-primary, #405189);
            border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-block;
            transition: transform 0.2s; align-self: flex-start;
        }
        .hosting-plans__promo-btn:hover { transform: scale(1.05); }
        .plan-card {
            background: var(--theme-card-bg, #fff); border-radius: 16px;
            border: 1px solid var(--theme-border-color, #e2e8f0);
            padding: 32px 24px; position: relative; transition: all 0.3s; display: flex; flex-direction: column;
        }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
        .plan-card--popular { border-color: var(--theme-primary, #405189); box-shadow: 0 8px 40px rgba(64,81,137,0.15); }
        .plan-card__badge {
            position: absolute; top: -1px; right: 24px; padding: 4px 14px;
            background: var(--theme-primary, #405189); color: #fff; font-size: 11px; font-weight: 700;
            border-radius: 0 0 8px 8px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .plan-card__icon { font-size: 32px; color: var(--theme-primary, #405189); margin-bottom: 12px; }
        .plan-card__name { font-size: 20px; font-weight: 800; margin-bottom: 4px; color: var(--theme-text-color, #1e293b); }
        .plan-card__subtitle { font-size: 13px; color: var(--theme-muted-color, #94a3b8); margin-bottom: 20px; }
        .plan-card__pricing { margin-bottom: 20px; }
        .plan-card__old-price { font-size: 14px; color: var(--theme-muted-color, #94a3b8); text-decoration: line-through; }
        .plan-card__price { font-size: 40px; font-weight: 900; color: var(--theme-text-color, #1e293b); line-height: 1; }
        .plan-card__price small { font-size: 15px; font-weight: 600; color: var(--theme-muted-color, #94a3b8); }
        .plan-card__discount {
            display: inline-block; padding: 3px 10px; background: rgba(16,185,129,0.1);
            color: var(--theme-success, #10b981); border-radius: 6px; font-size: 12px; font-weight: 700; margin-top: 8px;
        }
        .plan-card__features { flex: 1; margin-bottom: 24px; }
        .plan-card__feature {
            display: flex; align-items: center; gap: 10px; padding: 10px 0;
            border-bottom: 1px solid var(--theme-border-color, #e2e8f0);
            font-size: 14px; color: var(--theme-text-color, #1e293b);
        }
        .plan-card__feature:last-child { border-bottom: none; }
        .plan-card__feature i { color: var(--theme-success, #10b981); font-size: 16px; width: 20px; text-align: center; }
        .plan-card__feature span { font-weight: 600; color: var(--theme-text-color, #1e293b); }
        .plan-card__cp { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 13px; color: var(--theme-muted-color, #94a3b8); font-weight: 600; }
        .plan-card__cp i { font-size: 18px; color: var(--theme-primary, #405189); }
        .plan-card__btn {
            width: 100%; padding: 14px; text-align: center; border-radius: 10px;
            font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.25s; border: none; font-family: inherit;
        }
        .plan-card__btn--primary { background: var(--theme-primary, #405189); color: #fff; }
        .plan-card__btn--primary:hover { filter: brightness(1.1); transform: translateY(-2px); }
        .plan-card__btn--outline { background: transparent; color: var(--theme-primary, #405189); border: 2px solid var(--theme-border-color, #e2e8f0); }
        .plan-card__btn--outline:hover { border-color: var(--theme-primary, #405189); background: var(--theme-primary, #405189); color: #fff; }

        /* ===== 8. INFRASTRUCTURE ===== */
        .infra { padding: 80px 0; }
        .infra__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .infra-card {
            background: var(--theme-card-bg, #fff); border-radius: 16px; padding: 32px;
            border: 1px solid var(--theme-border-color, #e2e8f0);
            transition: all 0.3s;
        }
        .infra-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
        .infra-card__icon {
            width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 20px;
        }
        .infra-card__icon--1 { background: rgba(64,81,137,0.1); color: var(--theme-primary, #405189); }
        .infra-card__icon--2 { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .infra-card__icon--3 { background: rgba(99,102,241,0.1); color: var(--theme-accent, #6366f1); }
        .infra-card__icon--4 { background: rgba(16,185,129,0.1); color: var(--theme-success, #10b981); }
        .infra-card__icon--5 { background: rgba(239,68,68,0.1); color: #ef4444; }
        .infra-card__icon--6 { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .infra-card__title { font-size: 18px; font-weight: 800; margin-bottom: 8px; color: var(--theme-text-color, #1e293b); }
        .infra-card__desc { font-size: 14px; color: var(--theme-muted-color, #64748b); line-height: 1.7; }

        /* ===== 9. STATS COUNTER ===== */
        .stats {
            padding: 60px 0;
            background: linear-gradient(135deg, var(--theme-hero-bg-start, #0c1222) 0%, var(--theme-hero-bg-mid, #162447) 50%, var(--theme-hero-bg-end, #1a1a3e) 100%);
            position: relative; overflow: hidden;
        }
        .stats::before {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .stats__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; position: relative; z-index: 2; }
        .stats__item { text-align: center; }
        .stats__number { font-size: 48px; font-weight: 900; color: #fff; margin-bottom: 4px; }
        .stats__number span { color: var(--theme-welcome-accent, #10b981); }
        .stats__label { font-size: 15px; color: rgba(255,255,255,0.6); font-weight: 600; }

        /* ===== 10. VPS PLANS ===== */
        .vps { padding: 80px 0; background: var(--theme-body-bg, #f7f9fc); }
        .vps__layout { display: grid; grid-template-columns: 300px 1fr; gap: 40px; align-items: center; }
        .vps__visual {
            background: linear-gradient(135deg, var(--theme-primary, #405189), var(--theme-accent, #6366f1));
            border-radius: 20px; padding: 40px; display: flex; flex-direction: column; align-items: center;
            justify-content: center; min-height: 360px; color: #fff; text-align: center;
        }
        .vps__visual h3 { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
        .vps__visual p { font-size: 14px; opacity: 0.75; }
        .vps__server-rack { display: flex; flex-direction: column; gap: 8px; width: 200px; margin: 0 auto 20px; }
        .vps__rack-unit { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 6px; padding: 10px 12px; }
        .vps__rack-led { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .vps__rack-led--on { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
        .vps__rack-led--blink { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; animation: ledBlink 1.5s ease-in-out infinite; }
        .vps__rack-slots { flex: 1; height: 4px; background: repeating-linear-gradient(90deg, rgba(255,255,255,0.15) 0px, rgba(255,255,255,0.15) 8px, transparent 8px, transparent 12px); border-radius: 2px; }
        @keyframes ledBlink { 0%,100% { opacity: 1; } 50% { opacity: 0.2; } }
        .vps__grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .vps-card {
            background: var(--theme-card-bg, #fff); border-radius: 14px; padding: 24px;
            border: 1px solid var(--theme-border-color, #e2e8f0); transition: all 0.3s; position: relative;
        }
        .vps-card:hover { transform: translateY(-4px); box-shadow: 0 15px 50px rgba(0,0,0,0.08); }
        .vps-card__badge {
            position: absolute; top: 12px; right: 12px; padding: 3px 10px;
            border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase;
        }
        .vps-card__badge--popular { background: var(--theme-primary, #405189); color: #fff; }
        .vps-card__badge--value { background: var(--theme-welcome-accent, #10b981); color: #fff; }
        .vps-card__name { font-size: 18px; font-weight: 800; color: var(--theme-text-color, #1e293b); margin-bottom: 12px; }
        .vps-card__specs { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
        .vps-card__spec { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--theme-muted-color, #64748b); }
        .vps-card__spec i { color: var(--theme-primary, #405189); font-size: 16px; width: 18px; text-align: center; }
        .vps-card__price { font-size: 28px; font-weight: 900; color: var(--theme-text-color, #1e293b); }
        .vps-card__price small { font-size: 14px; font-weight: 600; color: var(--theme-muted-color, #94a3b8); }
        .vps-card__btn {
            display: block; width: 100%; margin-top: 12px; padding: 10px; text-align: center;
            background: var(--theme-body-bg, #f7f9fc); color: var(--theme-primary, #405189);
            border-radius: 8px; font-weight: 700; font-size: 13px; transition: all 0.2s;
        }
        .vps-card__btn:hover { background: var(--theme-primary, #405189); color: #fff; }

        /* ===== 11. TESTIMONIALS ===== */
        .testimonials { padding: 80px 0; }
        .testimonials__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .testimonial-card {
            background: var(--theme-card-bg, #fff); border-radius: 16px; padding: 32px;
            border: 1px solid var(--theme-border-color, #e2e8f0); transition: all 0.3s;
        }
        .testimonial-card:hover { transform: translateY(-4px); box-shadow: 0 15px 50px rgba(0,0,0,0.06); }
        .testimonial-card__stars { margin-bottom: 16px; color: #f59e0b; font-size: 16px; display: flex; gap: 2px; }
        .testimonial-card__text { font-size: 15px; color: var(--theme-text-color, #1e293b); line-height: 1.7; margin-bottom: 20px; font-style: italic; }
        .testimonial-card__author { display: flex; align-items: center; gap: 12px; }
        .testimonial-card__avatar {
            width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800; color: #fff;
        }
        .testimonial-card__avatar--1 { background: var(--theme-primary, #405189); }
        .testimonial-card__avatar--2 { background: var(--theme-accent, #6366f1); }
        .testimonial-card__avatar--3 { background: var(--theme-welcome-accent, #10b981); }
        .testimonial-card__name { font-size: 15px; font-weight: 700; color: var(--theme-text-color, #1e293b); }
        .testimonial-card__role { font-size: 13px; color: var(--theme-muted-color, #94a3b8); }

        /* ===== 12. FAQ ===== */
        .faq { padding: 80px 0; background: var(--theme-body-bg, #f7f9fc); }
        .faq__list { max-width: 760px; margin: 0 auto; }
        .faq__item {
            background: var(--theme-card-bg, #fff); border-radius: 12px;
            border: 1px solid var(--theme-border-color, #e2e8f0);
            margin-bottom: 12px; overflow: hidden; transition: box-shadow 0.3s;
        }
        .faq__item:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        .faq__question {
            width: 100%; display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; background: none; border: none; cursor: pointer;
            font-size: 16px; font-weight: 700; color: var(--theme-text-color, #1e293b);
            text-align: left; font-family: inherit;
        }
        .faq__question i { font-size: 18px; color: var(--theme-muted-color, #94a3b8); transition: transform 0.3s; }
        .faq__answer { padding: 0 24px 20px; font-size: 15px; color: var(--theme-muted-color, #64748b); line-height: 1.7; }

        /* ===== 13. CTA BANNER ===== */
        .cta-banner {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--theme-hero-bg-start, #0c1222), var(--theme-hero-bg-end, #1a1a3e));
            text-align: center; position: relative; overflow: hidden;
        }
        .cta-banner::before {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .cta-banner__inner { position: relative; z-index: 2; }
        .cta-banner__title { font-size: 40px; font-weight: 900; color: #fff; margin-bottom: 16px; }
        .cta-banner__desc { font-size: 18px; color: rgba(255,255,255,0.6); max-width: 520px; margin: 0 auto 32px; }
        .cta-banner__note { font-size: 14px; color: rgba(255,255,255,0.4); margin-top: 16px; }

        /* ===== 14. FOOTER ===== */
        .footer { background: var(--theme-footer-bg, #0f1117); color: rgba(255,255,255,0.6); padding: 60px 0 0; }
        .footer__grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 48px; }
        .footer__brand { font-size: 22px; font-weight: 900; color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 4px; }
        .footer__brand-dot { width: 8px; height: 8px; background: var(--theme-welcome-accent, #10b981); border-radius: 50%; }
        .footer__desc { font-size: 14px; line-height: 1.7; margin-bottom: 20px; }
        .footer__contact-item { display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 10px; }
        .footer__contact-item i { color: var(--theme-welcome-accent, #10b981); font-size: 16px; width: 20px; text-align: center; }
        .footer__col-title { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .footer__link { display: block; font-size: 14px; margin-bottom: 10px; transition: color 0.2s; }
        .footer__link:hover { color: #fff; }
        .footer__bottom {
            border-top: 1px solid rgba(255,255,255,0.08); padding: 20px 0;
            display: flex; align-items: center; justify-content: space-between; font-size: 13px;
        }
        .footer__payments { display: flex; gap: 12px; align-items: center; }
        .footer__payment-icon {
            width: 42px; height: 28px; background: rgba(255,255,255,0.08); border-radius: 4px;
            display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .hero__inner { grid-template-columns: 1fr; text-align: center; }
            .hero__visual { display: none; }
            .hero__desc { margin-left: auto; margin-right: auto; }
            .hero__stats { justify-content: center; }
            .hosting-plans__grid { grid-template-columns: 1fr 1fr; }
            .hosting-plans__promo { grid-column: 1 / -1; }
            .vps__layout { grid-template-columns: 1fr; }
            .vps__visual { display: none; }
            .main-nav__mega { min-width: 480px; }
        }
        @media (max-width: 768px) {
            .section-title { font-size: 28px; }
            .top-bar__left { display: none; }
            .main-nav__menu { display: none; }
            .main-nav__hamburger { display: block; }
            .main-nav__mobile-menu {
                position: fixed; top: 64px; left: 0; right: 0; bottom: 0;
                background: var(--theme-nav-bg, #0f1117); padding: 24px; overflow-y: auto; z-index: 140;
            }
            .main-nav__mobile-menu a {
                display: block; padding: 14px 0; color: rgba(255,255,255,0.7);
                font-size: 16px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.06);
            }
            .hero { padding: 60px 0; }
            .hero__title { font-size: 34px; }
            .hero__title-sub { font-size: 20px; }
            .hero__stats { flex-direction: column; align-items: center; }
            .promo-cards__grid { grid-template-columns: 1fr 1fr; }
            .hosting-plans__grid { grid-template-columns: 1fr; }
            .infra__grid { grid-template-columns: 1fr; }
            .stats__grid { grid-template-columns: repeat(2, 1fr); gap: 32px; }
            .stats__number { font-size: 36px; }
            .vps__grid { grid-template-columns: 1fr; }
            .testimonials__grid { grid-template-columns: 1fr; }
            .footer__grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .promo-cards__grid { grid-template-columns: 1fr; }
            .footer__grid { grid-template-columns: 1fr; }
            .footer__bottom { flex-direction: column; gap: 12px; text-align: center; }
            .hero__title { font-size: 28px; }
            .cta-banner__title { font-size: 28px; }
        }
    </style>
</head>
<body x-data="{ mobileMenu: false }">

    {{-- ===== 2. TOP BAR ===== --}}
    <div class="top-bar">
        <div class="container">
            <div class="top-bar__inner">
                <div class="top-bar__left">
                    <a href="mailto:info@panelica.com" class="top-bar__item"><i class="ri-mail-line"></i> info@panelica.com</a>
                    <div class="top-bar__divider"></div>
                    <a href="/client/contact" class="top-bar__item"><i class="ri-headphone-line"></i> Contact</a>
                    <div class="top-bar__divider"></div>
                    <a href="/client/tickets/create" class="top-bar__item"><i class="ri-ticket-line"></i> Support Ticket</a>
                    <div class="top-bar__divider"></div>
                    <a href="https://www.panelica.com/blog" class="top-bar__item"><i class="ri-article-line"></i> Blog</a>
                </div>
                <div class="top-bar__right">
                    <a href="{{ route('client.login') }}" class="top-bar__item"><i class="ri-user-line"></i> My Account</a>
                    <div class="top-bar__divider"></div>
                    <a href="{{ route('client.register') }}" class="top-bar__item"><i class="ri-user-add-line"></i> Sign Up</a>
                    <div class="top-bar__divider"></div>
                    <span class="top-bar__item"><i class="ri-global-line"></i> EN</span>
                    <div class="top-bar__divider"></div>
                    <span class="top-bar__item"><i class="ri-money-dollar-circle-line"></i> USD</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== 3. MAIN NAVIGATION ===== --}}
    <nav class="main-nav" x-data="{ scrolled: false }" @scroll.window="scrolled = (window.scrollY > 10)" :class="{ 'scrolled': scrolled }">
        <div class="container">
            <div class="main-nav__inner">
                {{-- Brand --}}
                <a href="{{ route('home') }}" class="main-nav__brand">
                    @if(!empty($customLogo))
                        <img src="{{ $customLogo }}" alt="PNLCS">
                    @else
                        PNLCS<span class="main-nav__brand-dot"></span>
                    @endif
                </a>

                {{-- Desktop Menu --}}
                <div class="main-nav__menu">
                    {{-- Domains --}}
                    <div class="main-nav__item">
                        <span class="main-nav__link">Domains <i class="ri-arrow-down-s-line"></i></span>
                        <div class="main-nav__dropdown">
                            <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-search-line"></i> Domain Search</a>
                            <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-exchange-line"></i> Domain Transfer</a>
                            <a href="/client/domain-search" class="main-nav__dropdown-link"><i class="ri-file-search-line"></i> WHOIS Lookup</a>
                        </div>
                    </div>
                    {{-- Hosting --}}
                    <div class="main-nav__item">
                        <span class="main-nav__link">Hosting <i class="ri-arrow-down-s-line"></i></span>
                        <div class="main-nav__mega" style="min-width: 480px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                                <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> Shared Hosting</a>
                                <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-wordpress-line"></i> WordPress Hosting</a>
                                <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-building-line"></i> Business Hosting</a>
                                <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-group-line"></i> Reseller Hosting</a>
                            </div>
                            <div class="main-nav__mega-promo">
                                <span class="main-nav__mega-promo-text"><i class="ri-gift-2-line"></i> Get 50% off your first hosting plan!</span>
                                <a href="{{ route('client.register') }}" class="main-nav__mega-promo-btn">Claim Now</a>
                            </div>
                        </div>
                    </div>
                    {{-- Servers --}}
                    <div class="main-nav__item">
                        <span class="main-nav__link">Servers <i class="ri-arrow-down-s-line"></i></span>
                        <div class="main-nav__dropdown">
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-cloud-line"></i> VPS Server</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-hard-drive-2-line"></i> VDS Server</a>
                            <a href="/client/store" class="main-nav__dropdown-link"><i class="ri-server-line"></i> Dedicated Server</a>
                        </div>
                    </div>
                    {{-- Knowledge Base --}}
                    <div class="main-nav__item">
                        <a href="/client/knowledgebase" class="main-nav__link" style="cursor:pointer;">Knowledge Base</a>
                    </div>
                    {{-- Store --}}
                    <div class="main-nav__item">
                        <a href="/client/store" class="main-nav__link" style="cursor:pointer;">Store</a>
                    </div>
                </div>

                {{-- Right --}}
                <div class="main-nav__right">
                    <a href="/client/cart" class="main-nav__cart"><i class="ri-shopping-cart-2-line"></i></a>
                    <a href="{{ route('client.login') }}" class="main-nav__login">Login</a>
                    <button class="main-nav__hamburger" @click="mobileMenu = !mobileMenu"><i class="ri-menu-line"></i></button>
                </div>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div class="main-nav__mobile-menu" x-show="mobileMenu" x-transition @click.away="mobileMenu = false" style="display: none;">
            <a href="/client/domain-search">Domain Search</a>
            <a href="/client/store">Hosting Plans</a>
            <a href="/client/store">VPS Server</a>
            <a href="/client/knowledgebase">Knowledge Base</a>
            <a href="/client/contact">Contact</a>
            <a href="{{ route('client.login') }}">Login</a>
            <a href="{{ route('client.register') }}">Sign Up</a>
        </div>
    </nav>

    {{-- ===== 4. HERO SECTION ===== --}}
    <section class="hero">
        <div class="hero__orb hero__orb--1"></div>
        <div class="hero__orb hero__orb--2"></div>
        <div class="hero__orb hero__orb--3"></div>
        <div class="container">
            <div class="hero__inner">
                <div>
                    <div class="hero__badge"><i class="ri-shield-check-line"></i> Powered by Panelica Infrastructure</div>
                    <h1 class="hero__title">Hosting That <span>Simply Works</span></h1>
                    <p class="hero__title-sub">Panelica-Powered Isolated Infrastructure</p>
                    <p class="hero__desc">Launch your website on Panelica's isolated hosting platform with NVMe storage, per-account resource limits, and free SSL — from $1.99/month.</p>
                    <div class="hero__stats">
                        <div class="hero__stat"><i class="ri-shield-user-line"></i> Cgroups v2 Isolation</div>
                        <div class="hero__stat"><i class="ri-speed-line"></i> Nginx + PHP-FPM</div>
                    </div>
                    <a href="{{ route('client.register') }}" class="btn-accent" style="font-size: 16px; padding: 16px 36px;">
                        Get Started Now <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                <div class="hero__visual">
                    <canvas id="heroCanvas" style="width:100%;max-width:480px;height:400px;"></canvas>
                    <div class="hero__callout hero__callout--1">
                        <i class="ri-gift-line"></i> FREE Domain with annual plans
                    </div>
                    <div class="hero__callout hero__callout--2">
                        <i class="ri-lock-line"></i> Free SSL Included
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 5. DOMAIN SEARCH ===== --}}
    <section class="domain-search">
        <div class="container">
            <h2 class="section-title">Find Your Perfect Domain</h2>
            <p class="section-subtitle">Search for your ideal domain name and secure it today</p>
            <form action="/client/domain-search" method="GET" class="domain-search__bar">
                <div class="domain-search__icon"><i class="ri-global-line"></i></div>
                <input type="text" name="domain" class="domain-search__input" placeholder="Enter your domain name... (e.g. mysite.com)" required>
                <button type="submit" class="domain-search__btn"><i class="ri-search-line"></i> Search</button>
            </form>
            <div class="domain-search__extensions">
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.com</div>
                    <div class="domain-search__ext-price">$9.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.net</div>
                    <div class="domain-search__ext-price">$11.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.org</div>
                    <div class="domain-search__ext-price">$8.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.io</div>
                    <div class="domain-search__ext-price">$29.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.dev</div>
                    <div class="domain-search__ext-price">$12.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.co</div>
                    <div class="domain-search__ext-price">$11.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.biz</div>
                    <div class="domain-search__ext-price">$14.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
                <div class="domain-search__ext">
                    <div class="domain-search__ext-name">.info</div>
                    <div class="domain-search__ext-price">$4.99/yr</div>
                    <span class="domain-search__ext-link">Register</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 6. PROMO SERVICE CARDS ===== --}}
    <section class="promo-cards">
        <div class="container">
            <div class="promo-cards__grid">
                <div class="promo-card promo-card--1">
                    <div class="promo-card__title">.COM Domain</div>
                    <div class="promo-card__subtitle">Register your brand today</div>
                    <div class="promo-card__prices">
                        <span class="promo-card__old">$12.99</span>
                        <span class="promo-card__new">$9.99<small>/yr</small></span>
                    </div>
                    <a href="/client/domain-search" class="promo-card__cta">Register Now</a>
                </div>
                <div class="promo-card promo-card--2">
                    <div class="promo-card__title">Web Hosting</div>
                    <div class="promo-card__subtitle">NVMe powered hosting</div>
                    <div class="promo-card__prices">
                        <span class="promo-card__old">$4.99</span>
                        <span class="promo-card__new">$2.99<small>/mo</small></span>
                    </div>
                    <a href="/client/store" class="promo-card__cta">Get Started</a>
                </div>
                <div class="promo-card promo-card--3">
                    <div class="promo-card__title">WordPress Hosting</div>
                    <div class="promo-card__subtitle">Optimized for WordPress</div>
                    <div class="promo-card__prices">
                        <span class="promo-card__old">$5.99</span>
                        <span class="promo-card__new">$3.99<small>/mo</small></span>
                    </div>
                    <a href="/client/store" class="promo-card__cta">Get Started</a>
                </div>
                <div class="promo-card promo-card--4">
                    <div class="promo-card__title">VPS Server</div>
                    <div class="promo-card__subtitle">Full root access included</div>
                    <div class="promo-card__prices">
                        <span class="promo-card__old">$12.00</span>
                        <span class="promo-card__new">$6.99<small>/mo</small></span>
                    </div>
                    <a href="/client/store" class="promo-card__cta">Configure</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 7. HOSTING PLANS ===== --}}
    <section class="hosting-plans">
        <div class="container">
            <h2 class="section-title">Popular Web Hosting Plans</h2>
            <p class="section-subtitle">Choose the perfect plan for your website. Upgrade or downgrade anytime.</p>
            <div class="hosting-plans__grid">
                {{-- Promo sidebar --}}
                <div class="hosting-plans__promo">
                    <i class="ri-gift-2-line hosting-plans__promo-icon"></i>
                    <h3>FREE .COM Domain with Annual Plans</h3>
                    <p>Get a free domain registration when you sign up for any annual hosting plan. No hidden fees.</p>
                    <a href="{{ route('client.register') }}" class="hosting-plans__promo-btn">Claim Offer</a>
                </div>

                {{-- Starter --}}
                <div class="plan-card">
                    <div class="plan-card__icon"><i class="ri-rocket-line"></i></div>
                    <div class="plan-card__name">Starter</div>
                    <div class="plan-card__subtitle">Perfect for personal sites</div>
                    <div class="plan-card__pricing">
                        <div class="plan-card__old-price">$3.99/mo</div>
                        <div class="plan-card__price">$1.99 <small>/mo</small></div>
                        <div class="plan-card__discount">50% OFF</div>
                    </div>
                    <div class="plan-card__features">
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>1</span> Website</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>10 GB</span> NVMe Storage</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unmetered</span> Bandwidth</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>5</span> Email Accounts</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>2</span> Databases</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> PHP <span>8.3</span></div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>1</span> CPU Core</div>
                    </div>
                    <div class="plan-card__cp"><i class="ri-dashboard-line"></i> Panelica Panel</div>
                    <a href="/client/store" class="plan-card__btn plan-card__btn--outline">Get Started</a>
                </div>

                {{-- Professional --}}
                <div class="plan-card plan-card--popular">
                    <div class="plan-card__badge">Popular</div>
                    <div class="plan-card__icon"><i class="ri-flashlight-line"></i></div>
                    <div class="plan-card__name">Professional</div>
                    <div class="plan-card__subtitle">Ideal for growing businesses</div>
                    <div class="plan-card__pricing">
                        <div class="plan-card__old-price">$7.99/mo</div>
                        <div class="plan-card__price">$3.99 <small>/mo</small></div>
                        <div class="plan-card__discount">50% OFF</div>
                    </div>
                    <div class="plan-card__features">
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Websites</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>50 GB</span> NVMe Storage</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unmetered</span> Bandwidth</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Email</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Databases</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> PHP <span>8.4</span></div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>2</span> CPU Cores</div>
                    </div>
                    <div class="plan-card__cp"><i class="ri-dashboard-line"></i> Panelica Panel</div>
                    <a href="/client/store" class="plan-card__btn plan-card__btn--primary">Get Started</a>
                </div>

                {{-- Business --}}
                <div class="plan-card">
                    <div class="plan-card__icon"><i class="ri-building-2-line"></i></div>
                    <div class="plan-card__name">Business</div>
                    <div class="plan-card__subtitle">For high-traffic websites</div>
                    <div class="plan-card__pricing">
                        <div class="plan-card__old-price">$13.99/mo</div>
                        <div class="plan-card__price">$7.99 <small>/mo</small></div>
                        <div class="plan-card__discount">43% OFF</div>
                    </div>
                    <div class="plan-card__features">
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Websites</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>100 GB</span> NVMe Storage</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unmetered</span> Bandwidth</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Email</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>Unlimited</span> Databases</div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> PHP <span>8.4</span></div>
                        <div class="plan-card__feature"><i class="ri-check-line"></i> <span>4</span> CPU Cores</div>
                    </div>
                    <div class="plan-card__cp"><i class="ri-dashboard-line"></i> Panelica Panel</div>
                    <a href="/client/store" class="plan-card__btn plan-card__btn--outline">Get Started</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 8. INFRASTRUCTURE ===== --}}
    <section class="infra">
        <div class="container">
            <h2 class="section-title">Enterprise-Grade Infrastructure</h2>
            <p class="section-subtitle">Built for performance, reliability, and security from the ground up</p>
            <div class="infra__grid">
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--1"><i class="ri-hard-drive-3-line"></i></div>
                    <div class="infra-card__title">NVMe Storage</div>
                    <div class="infra-card__desc">Every account sits on enterprise NVMe drives — dramatically faster read/write than spinning disks, so your pages load in a flash.</div>
                </div>
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--2"><i class="ri-speed-line"></i></div>
                    <div class="infra-card__title">Nginx + PHP-FPM Stack</div>
                    <div class="infra-card__desc">Powered by Nginx reverse proxy with per-user PHP-FPM pools. Each site gets its own process, tuned for speed and stability.</div>
                </div>
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--3"><i class="ri-shield-user-line"></i></div>
                    <div class="infra-card__title">Cgroups v2 Isolation</div>
                    <div class="infra-card__desc">Panelica enforces per-user CPU, memory, and I/O limits through Linux Cgroups v2. Your resources are yours alone — no shared bottlenecks.</div>
                </div>
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--4"><i class="ri-dashboard-line"></i></div>
                    <div class="infra-card__title">Panelica Control Panel</div>
                    <div class="infra-card__desc">Our own modern panel for managing domains, emails, databases, files, and DNS — clean UI, fast, and built specifically for this platform.</div>
                </div>
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--5"><i class="ri-lock-line"></i></div>
                    <div class="infra-card__title">Free SSL & Security</div>
                    <div class="infra-card__desc">Every domain gets a free SSL certificate, along with DDoS protection, web application firewall, and real-time malware scanning.</div>
                </div>
                <div class="infra-card">
                    <div class="infra-card__icon infra-card__icon--6"><i class="ri-equalizer-line"></i></div>
                    <div class="infra-card__title">Dedicated Resources</div>
                    <div class="infra-card__desc">Every plan has defined CPU, RAM, and storage quotas enforced at the kernel level. You always get the full capacity you paid for.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 9. STATS COUNTER ===== --}}
    <section class="stats">
        <div class="container">
            <div class="stats__grid">
                <div class="stats__item">
                    <div class="stats__number">5<span>+</span></div>
                    <div class="stats__label">PHP Versions</div>
                </div>
                <div class="stats__item">
                    <div class="stats__number">20<span>+</span></div>
                    <div class="stats__label">Managed Services</div>
                </div>
                <div class="stats__item">
                    <div class="stats__number">24<span>/7</span></div>
                    <div class="stats__label">Monitoring & Alerts</div>
                </div>
                <div class="stats__item">
                    <div class="stats__number">100<span>%</span></div>
                    <div class="stats__label">Resource Isolation</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 10. VPS PLANS ===== --}}
    <section class="vps">
        <div class="container">
            <h2 class="section-title">VPS Server Plans</h2>
            <p class="section-subtitle">Full root access, dedicated resources, and instant deployment</p>
            <div class="vps__layout">
                <div class="vps__visual">
                    <div class="vps__server-rack">
                        <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span><span class="vps__rack-led vps__rack-led--blink"></span></div>
                        <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-led vps__rack-led--blink"></span><span class="vps__rack-slots"></span><span class="vps__rack-led vps__rack-led--on"></span></div>
                        <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--blink"></span><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span><span class="vps__rack-led vps__rack-led--on"></span></div>
                        <div class="vps__rack-unit"><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-led vps__rack-led--on"></span><span class="vps__rack-slots"></span><span class="vps__rack-led vps__rack-led--blink"></span></div>
                    </div>
                    <h3>Cloud VPS</h3>
                    <p>Full root access, dedicated CPU &amp; RAM, and instant provisioning on Panelica infrastructure</p>
                </div>
                <div class="vps__grid">
                    <div class="vps-card">
                        <div class="vps-card__name">VPS-1</div>
                        <div class="vps-card__specs">
                            <div class="vps-card__spec"><i class="ri-cpu-line"></i> 2 CPU Cores</div>
                            <div class="vps-card__spec"><i class="ri-ram-line"></i> 4 GB RAM</div>
                            <div class="vps-card__spec"><i class="ri-hard-drive-3-line"></i> 40 GB NVMe</div>
                        </div>
                        <div class="vps-card__price">$6.99 <small>/mo</small></div>
                        <a href="/client/store" class="vps-card__btn">Get Started</a>
                    </div>
                    <div class="vps-card">
                        <div class="vps-card__badge vps-card__badge--popular">Popular</div>
                        <div class="vps-card__name">VPS-2</div>
                        <div class="vps-card__specs">
                            <div class="vps-card__spec"><i class="ri-cpu-line"></i> 4 CPU Cores</div>
                            <div class="vps-card__spec"><i class="ri-ram-line"></i> 6 GB RAM</div>
                            <div class="vps-card__spec"><i class="ri-hard-drive-3-line"></i> 60 GB NVMe</div>
                        </div>
                        <div class="vps-card__price">$9.99 <small>/mo</small></div>
                        <a href="/client/store" class="vps-card__btn">Get Started</a>
                    </div>
                    <div class="vps-card">
                        <div class="vps-card__name">VPS-3</div>
                        <div class="vps-card__specs">
                            <div class="vps-card__spec"><i class="ri-cpu-line"></i> 4 CPU Cores</div>
                            <div class="vps-card__spec"><i class="ri-ram-line"></i> 8 GB RAM</div>
                            <div class="vps-card__spec"><i class="ri-hard-drive-3-line"></i> 80 GB NVMe</div>
                        </div>
                        <div class="vps-card__price">$14.99 <small>/mo</small></div>
                        <a href="/client/store" class="vps-card__btn">Get Started</a>
                    </div>
                    <div class="vps-card">
                        <div class="vps-card__badge vps-card__badge--value">Best Value</div>
                        <div class="vps-card__name">VPS-4</div>
                        <div class="vps-card__specs">
                            <div class="vps-card__spec"><i class="ri-cpu-line"></i> 6 CPU Cores</div>
                            <div class="vps-card__spec"><i class="ri-ram-line"></i> 12 GB RAM</div>
                            <div class="vps-card__spec"><i class="ri-hard-drive-3-line"></i> 120 GB NVMe</div>
                        </div>
                        <div class="vps-card__price">$19.99 <small>/mo</small></div>
                        <a href="/client/store" class="vps-card__btn">Get Started</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 11. TESTIMONIALS ===== --}}
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Customers Say</h2>
            <p class="section-subtitle">Trusted by thousands of businesses worldwide</p>
            <div class="testimonials__grid">
                <div class="testimonial-card">
                    <div class="testimonial-card__stars">
                        <i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i>
                    </div>
                    <p class="testimonial-card__text">"We migrated 15 websites from our old host and the speed improvement was immediately noticeable. Page load times dropped by over 60%. Support team helped with every step of the migration."</p>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--1">JM</div>
                        <div>
                            <div class="testimonial-card__name">James Mitchell</div>
                            <div class="testimonial-card__role">CTO, TechStart Inc.</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-card__stars">
                        <i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i>
                    </div>
                    <p class="testimonial-card__text">"The VPS performance is incredible for the price. Full root access, NVMe storage, and their uptime has been flawless for the past 14 months. Best hosting decision we've made."</p>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--2">SR</div>
                        <div>
                            <div class="testimonial-card__name">Sarah Rodriguez</div>
                            <div class="testimonial-card__role">Founder, DigitalCraft Agency</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-card__stars">
                        <i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i><i class="ri-star-fill"></i>
                    </div>
                    <p class="testimonial-card__text">"I run a small web agency and needed a platform I could count on. The reseller setup with Panelica's billing tools made onboarding my clients painless. Solid uptime and great ticket support."</p>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--3">AK</div>
                        <div>
                            <div class="testimonial-card__name">Alex Kim</div>
                            <div class="testimonial-card__role">CEO, CloudNine Hosting</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 12. FAQ ===== --}}
    <section class="faq">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Everything you need to know about our hosting services</p>
            <div class="faq__list" x-data="{ open: null }">
                <div class="faq__item">
                    <button class="faq__question" @click="open = open === 1 ? null : 1">
                        What is included with my hosting plan?
                        <i class="ri-add-line" :class="{ 'ri-subtract-line': open === 1, 'ri-add-line': open !== 1 }"></i>
                    </button>
                    <div class="faq__answer" x-show="open === 1" x-collapse>
                        Every hosting plan includes free SSL certificates, NVMe SSD storage, unmetered bandwidth, Panelica panel access, email accounts, one-click WordPress installer, daily backups, and 24/7 technical support. Higher-tier plans also include priority support and additional server resources.
                    </div>
                </div>
                <div class="faq__item">
                    <button class="faq__question" @click="open = open === 2 ? null : 2">
                        Can I upgrade my plan later?
                        <i class="ri-add-line" :class="{ 'ri-subtract-line': open === 2, 'ri-add-line': open !== 2 }"></i>
                    </button>
                    <div class="faq__answer" x-show="open === 2" x-collapse>
                        Absolutely! You can upgrade or downgrade your hosting plan at any time directly from your client dashboard. The price difference will be prorated, so you only pay for what you use. No downtime is involved during the upgrade process.
                    </div>
                </div>
                <div class="faq__item">
                    <button class="faq__question" @click="open = open === 3 ? null : 3">
                        Do you offer a money-back guarantee?
                        <i class="ri-add-line" :class="{ 'ri-subtract-line': open === 3, 'ri-add-line': open !== 3 }"></i>
                    </button>
                    <div class="faq__answer" x-show="open === 3" x-collapse>
                        Yes, we offer a 30-day money-back guarantee on all shared and WordPress hosting plans. If you're not satisfied with our service for any reason, you can request a full refund within the first 30 days. VPS and dedicated server plans have a 7-day money-back guarantee.
                    </div>
                </div>
                <div class="faq__item">
                    <button class="faq__question" @click="open = open === 4 ? null : 4">
                        Will you help me migrate my website?
                        <i class="ri-add-line" :class="{ 'ri-subtract-line': open === 4, 'ri-add-line': open !== 4 }"></i>
                    </button>
                    <div class="faq__answer" x-show="open === 4" x-collapse>
                        Yes! We offer free website migration for all new customers. Our team will handle the entire process — files, databases, emails, and DNS. We can migrate from any platform including cPanel, Plesk, or DirectAdmin into your new Panelica-powered account with zero downtime.
                    </div>
                </div>
                <div class="faq__item">
                    <button class="faq__question" @click="open = open === 5 ? null : 5">
                        What kind of support do you offer?
                        <i class="ri-add-line" :class="{ 'ri-subtract-line': open === 5, 'ri-add-line': open !== 5 }"></i>
                    </button>
                    <div class="faq__answer" x-show="open === 5" x-collapse>
                        We provide 24/7 technical support through live chat, support tickets, and phone. Our support team consists of experienced system administrators and developers who can assist with server configuration, performance optimization, security issues, and more.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== 13. CTA BANNER ===== --}}
    <section class="cta-banner">
        <div class="container">
            <div class="cta-banner__inner">
                <h2 class="cta-banner__title">Ready to Get Started?</h2>
                <p class="cta-banner__desc">Join thousands of satisfied customers and take your online presence to the next level with our hosting solutions.</p>
                <a href="{{ route('client.register') }}" class="btn-accent" style="font-size: 17px; padding: 18px 42px;">
                    Start Your Journey <i class="ri-arrow-right-line"></i>
                </a>
                <p class="cta-banner__note"><i class="ri-shield-check-line"></i> No credit card required. 30-day money-back guarantee.</p>
            </div>
        </div>
    </section>

    {{-- ===== 14. FOOTER ===== --}}
    <footer class="footer">
        <div class="container">
            <div class="footer__grid">
                {{-- Brand --}}
                <div>
                    <div class="footer__brand">
                        @if(!empty($customLogo))
                            <img src="{{ $customLogo }}" alt="PNLCS" style="height: 28px;">
                        @else
                            PNLCS<span class="footer__brand-dot"></span>
                        @endif
                    </div>
                    <p class="footer__desc">PNLCS is the billing &amp; hosting management platform by Panelica. Domains, hosting, VPS, and SSL — all under one roof.</p>
                    <div class="footer__contact-item"><i class="ri-mail-line"></i> info@panelica.com</div>
                    <div class="footer__contact-item"><i class="ri-customer-service-line"></i> support@panelica.com</div>
                    <div class="footer__contact-item"><i class="ri-global-line"></i> panelica.com</div>
                </div>
                {{-- Domains --}}
                <div>
                    <div class="footer__col-title">Domains</div>
                    <a href="/client/domain-search" class="footer__link">Domain Search</a>
                    <a href="/client/domain-search" class="footer__link">Domain Transfer</a>
                    <a href="/client/domain-search" class="footer__link">WHOIS Lookup</a>
                </div>
                {{-- Hosting --}}
                <div>
                    <div class="footer__col-title">Hosting</div>
                    <a href="/client/store" class="footer__link">Shared Hosting</a>
                    <a href="/client/store" class="footer__link">WordPress Hosting</a>
                    <a href="/client/store" class="footer__link">Business Hosting</a>
                    <a href="/client/store" class="footer__link">Reseller Hosting</a>
                    <a href="/client/store" class="footer__link">VPS Server</a>
                </div>
                {{-- Support --}}
                <div>
                    <div class="footer__col-title">Support</div>
                    <a href="/client/knowledgebase" class="footer__link">Knowledge Base</a>
                    <a href="/client/announcements" class="footer__link">Announcements</a>
                    <a href="/client/contact" class="footer__link">Contact Us</a>
                    <a href="https://www.panelica.com" class="footer__link">Panelica.com</a>
                    <a href="https://www.panelica.com/blog" class="footer__link">Blog</a>
                </div>
            </div>
            <div class="footer__bottom">
                <span>&copy; {{ date('Y') }} PNLCS. All rights reserved.</span>
                <div class="footer__payments">
                    <div class="footer__payment-icon">VISA</div>
                    <div class="footer__payment-icon">MC</div>
                    <div class="footer__payment-icon">AMEX</div>
                    <div class="footer__payment-icon">PP</div>
                    <div class="footer__payment-icon">BTC</div>
                </div>
            </div>
        </div>
    </footer>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Three.js Globe Animation --}}
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script>
    (function() {
        const canvas = document.getElementById('heroCanvas');
        if (!canvas) return;

        // Read theme colors from CSS vars
        const cs = getComputedStyle(document.documentElement);
        function themeColor(name, fallback) {
            const v = cs.getPropertyValue('--theme-' + name).trim();
            return v || fallback;
        }
        const colPrimary = new THREE.Color(themeColor('welcome-primary', '#2563eb'));
        const colAccent = new THREE.Color(themeColor('welcome-accent', '#10b981'));
        const colSecondary = new THREE.Color(themeColor('welcome-secondary', '#7c3aed'));

        // Setup
        const W = canvas.clientWidth, H = canvas.clientHeight;
        const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
        renderer.setSize(W, H);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, W / H, 0.1, 100);
        camera.position.z = 4.5;

        // Globe wireframe
        const globeGeo = new THREE.IcosahedronGeometry(1.5, 3);
        const globeMat = new THREE.MeshBasicMaterial({ color: colPrimary, wireframe: true, transparent: true, opacity: 0.18 });
        const globe = new THREE.Mesh(globeGeo, globeMat);
        scene.add(globe);

        // Inner glow sphere
        const glowGeo = new THREE.SphereGeometry(1.48, 32, 32);
        const glowMat = new THREE.MeshBasicMaterial({ color: colPrimary, transparent: true, opacity: 0.04 });
        scene.add(new THREE.Mesh(glowGeo, glowMat));

        // Dots on globe surface
        const dotCount = 120;
        const dotGeo = new THREE.BufferGeometry();
        const dotPos = new Float32Array(dotCount * 3);
        const dotCol = new Float32Array(dotCount * 3);
        for (let i = 0; i < dotCount; i++) {
            const phi = Math.acos(2 * Math.random() - 1);
            const theta = Math.random() * Math.PI * 2;
            const r = 1.52;
            dotPos[i*3] = r * Math.sin(phi) * Math.cos(theta);
            dotPos[i*3+1] = r * Math.sin(phi) * Math.sin(theta);
            dotPos[i*3+2] = r * Math.cos(phi);
            const c = [colAccent, colPrimary, colSecondary][i % 3];
            dotCol[i*3] = c.r; dotCol[i*3+1] = c.g; dotCol[i*3+2] = c.b;
        }
        dotGeo.setAttribute('position', new THREE.BufferAttribute(dotPos, 3));
        dotGeo.setAttribute('color', new THREE.BufferAttribute(dotCol, 3));
        const dotMat = new THREE.PointsMaterial({ size: 0.04, vertexColors: true, transparent: true, opacity: 0.85 });
        const dots = new THREE.Points(dotGeo, dotMat);
        scene.add(dots);

        // Orbiting rings
        function makeRing(radius, color, tilt) {
            const ringGeo = new THREE.RingGeometry(radius - 0.005, radius + 0.005, 128);
            const ringMat = new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.2, side: THREE.DoubleSide });
            const ring = new THREE.Mesh(ringGeo, ringMat);
            ring.rotation.x = tilt;
            return ring;
        }
        const ring1 = makeRing(2.0, colAccent, 1.2);
        const ring2 = makeRing(2.3, colSecondary, 0.6);
        scene.add(ring1, ring2);

        // Orbiting particles (satellites)
        const satGroup = new THREE.Group();
        const satCount = 6;
        const sats = [];
        for (let i = 0; i < satCount; i++) {
            const sg = new THREE.SphereGeometry(0.035, 8, 8);
            const sm = new THREE.MeshBasicMaterial({ color: i % 2 === 0 ? colAccent : colSecondary });
            const sat = new THREE.Mesh(sg, sm);
            sat.userData = {
                radius: 1.9 + Math.random() * 0.5,
                speed: 0.3 + Math.random() * 0.4,
                offset: (i / satCount) * Math.PI * 2,
                tilt: 0.5 + Math.random() * 1.2
            };
            satGroup.add(sat);
            sats.push(sat);
        }
        scene.add(satGroup);

        // Connection lines between nearby dots (pulse effect)
        const lineCount = 20;
        const lineMat = new THREE.LineBasicMaterial({ color: colAccent, transparent: true, opacity: 0.12 });
        const lineGroup = new THREE.Group();
        for (let i = 0; i < lineCount; i++) {
            const a = Math.floor(Math.random() * dotCount);
            const b = Math.floor(Math.random() * dotCount);
            const lg = new THREE.BufferGeometry().setFromPoints([
                new THREE.Vector3(dotPos[a*3], dotPos[a*3+1], dotPos[a*3+2]),
                new THREE.Vector3(dotPos[b*3], dotPos[b*3+1], dotPos[b*3+2])
            ]);
            lineGroup.add(new THREE.Line(lg, lineMat));
        }
        scene.add(lineGroup);

        // Mouse interaction
        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', function(e) {
            mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
        });

        // Animate
        let time = 0;
        function animate() {
            requestAnimationFrame(animate);
            time += 0.005;

            globe.rotation.y = time * 0.3 + mouseX * 0.15;
            globe.rotation.x = Math.sin(time * 0.2) * 0.1 + mouseY * 0.1;
            dots.rotation.y = globe.rotation.y;
            dots.rotation.x = globe.rotation.x;
            lineGroup.rotation.y = globe.rotation.y;
            lineGroup.rotation.x = globe.rotation.x;

            ring1.rotation.z = time * 0.15;
            ring2.rotation.z = -time * 0.1;

            // Satellites orbit
            for (const sat of sats) {
                const d = sat.userData;
                const t = time * d.speed + d.offset;
                sat.position.x = d.radius * Math.cos(t) * Math.cos(d.tilt * 0.3);
                sat.position.y = d.radius * Math.sin(t) * Math.sin(d.tilt);
                sat.position.z = d.radius * Math.sin(t) * Math.cos(d.tilt);
            }

            // Pulse line opacity
            lineMat.opacity = 0.08 + Math.sin(time * 3) * 0.06;

            renderer.render(scene, camera);
        }
        animate();

        // Resize handler
        window.addEventListener('resize', function() {
            const w = canvas.clientWidth, h = canvas.clientHeight;
            if (w && h) {
                renderer.setSize(w, h);
                camera.aspect = w / h;
                camera.updateProjectionMatrix();
            }
        });
    })();
    </script>
</body>
</html>
