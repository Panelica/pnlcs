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
        .top-bar__language { position: relative; }
        .top-bar__language-toggle { list-style: none; }
        .top-bar__language-toggle::-webkit-details-marker { display: none; }
        .top-bar__language-menu {
            position: absolute; right: 0; top: 100%; z-index: 50; min-width: 150px; padding: 6px;
            color: var(--theme-text-color, #1e293b); background: var(--theme-card-bg, #fff);
            border: 1px solid var(--theme-border-color, #e2e8f0); border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .top-bar__language-option { display: block; padding: 6px 9px; white-space: nowrap; }
        .top-bar__language-option:hover, .top-bar__language-option--active {
            color: var(--theme-primary, #405189); background: var(--theme-body-bg, #f7f9fc);
        }
        .top-bar__language-option--active { font-weight: 600; }

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
        /* ===== DARK MODE ===== */
        :root[data-theme="dark"] {
            --theme-body-bg: #0f172a;
            --theme-card-bg: #1e293b;
            --theme-text-color: #e2e8f0;
            --theme-heading-color: #f1f5f9;
            --theme-muted-color: #94a3b8;
            --theme-border-color: #334155;
            --theme-input-bg: #1e293b;
            --theme-input-border: #475569;
            --theme-table-header-bg: #1e293b;
            --theme-card-shadow: rgba(0,0,0,0.3);
            --theme-overlay-bg: rgba(0,0,0,0.7);
        }
        :root[data-theme="dark"] body {
            background: var(--theme-body-bg) !important;
            color: var(--theme-text-color) !important;
        }
        :root[data-theme="dark"] .section-title,
        :root[data-theme="dark"] .section-subtitle { color: var(--theme-text-color); }
        :root[data-theme="dark"] .plan-card,
        :root[data-theme="dark"] .infra-card,
        :root[data-theme="dark"] .testimonial-card,
        :root[data-theme="dark"] .vps-card,
        :root[data-theme="dark"] .faq__item {
            background: var(--theme-card-bg) !important;
            border-color: var(--theme-border-color) !important;
        }
        :root[data-theme="dark"] .plan-card__name,
        :root[data-theme="dark"] .infra-card__title,
        :root[data-theme="dark"] .vps-card__name,
        :root[data-theme="dark"] .vps-card__price,
        :root[data-theme="dark"] .plan-card__price,
        :root[data-theme="dark"] .faq__question,
        :root[data-theme="dark"] .testimonial-card__name,
        :root[data-theme="dark"] .testimonial-card__text {
            color: var(--theme-text-color) !important;
        }
        :root[data-theme="dark"] .plan-card__subtitle,
        :root[data-theme="dark"] .plan-card__price small,
        :root[data-theme="dark"] .infra-card__desc,
        :root[data-theme="dark"] .vps-card__spec,
        :root[data-theme="dark"] .faq__answer,
        :root[data-theme="dark"] .testimonial-card__role {
            color: var(--theme-muted-color) !important;
        }
        :root[data-theme="dark"] .plan-card__feature {
            color: var(--theme-text-color) !important;
            border-color: var(--theme-border-color) !important;
        }
        :root[data-theme="dark"] .plan-card__feature span { color: var(--theme-text-color) !important; }
        :root[data-theme="dark"] .plan-card__btn--outline {
            border-color: var(--theme-border-color) !important;
            color: var(--theme-text-color) !important;
        }
        :root[data-theme="dark"] .domain-search {
            background: var(--theme-body-bg) !important;
        }
        :root[data-theme="dark"] .domain-search__bar {
            background: var(--theme-card-bg) !important;
            border-color: var(--theme-border-color) !important;
        }
        :root[data-theme="dark"] .domain-search__input {
            color: var(--theme-text-color) !important;
        }
        :root[data-theme="dark"] .domain-search__ext {
            background: var(--theme-card-bg) !important;
            border-color: var(--theme-border-color) !important;
        }
        :root[data-theme="dark"] .domain-search__ext-name { color: var(--theme-text-color) !important; }
        :root[data-theme="dark"] .hosting-plans,
        :root[data-theme="dark"] .faq,
        :root[data-theme="dark"] .vps {
            background: var(--theme-body-bg) !important;
        }
        :root[data-theme="dark"] .hero__callout {
            background: var(--theme-card-bg) !important;
            color: var(--theme-text-color) !important;
        }
        :root[data-theme="dark"] .vps-card__btn {
            background: var(--theme-card-bg) !important;
        }
        /* Dark mode toggle button */
        .dark-toggle { transition: color 0.2s; }
        .dark-toggle:hover { color: #fff !important; }
    </style>
