<!DOCTYPE html>
<html lang="{{ $currentLocale ?? 'en' }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield("title", __("client.my_account")) - {{ company_name() }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{
            --primary:var(--theme-primary, #1a4d80);
            --primary-light:var(--theme-primary-light, #e8f0fb);
            --primary-dark:var(--theme-primary-dark, #143d66);
            --accent:var(--theme-success, #06d6a0);
            --accent-dark:#05b589;
            --danger:#ef4444;
            --warning:#f59e0b;
            --success:var(--theme-success, #10b981);
            --bg:#f8fafc;
            --card:#ffffff;
            --border:var(--theme-border-color, #e2e8f0);
            --text:var(--theme-text-color, #1e293b);
            --muted:var(--theme-muted-color, #64748b);
            --shadow:0 1px 3px rgba(0,0,0,0.08),0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:0 4px 12px rgba(0,0,0,0.10),0 2px 4px rgba(0,0,0,0.06);
            --radius:12px;
            --radius-sm:8px;
        }
        /* The page is a column the full height of the window, and the content
           stretches to fill it - so on a short page the footer sits at the
           bottom of the screen instead of floating mid-page under the form. */
        body{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;display:flex;flex-direction:column}

        /* ─── NAVBAR ─── */
        .pn-navbar{background:var(--card);border-bottom:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);position:sticky;top:0;z-index:1000}
        .pn-navbar-inner{max-width:1440px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:60px;gap:8px}
        .pn-brand{color:var(--primary);font-size:20px;font-weight:800;text-decoration:none;letter-spacing:-0.5px;flex-shrink:0;display:flex;align-items:center;gap:8px}
        .pn-brand-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;display:inline-block}
        .pn-nav{display:flex;align-items:center;gap:2px;flex:1;padding:0 16px}
        .pn-nav-item{position:relative}
        .pn-nav-link{display:flex;align-items:center;gap:5px;padding:7px 12px;font-size:13.5px;font-weight:500;color:var(--muted);text-decoration:none;border-radius:8px;transition:all 0.15s;white-space:nowrap;cursor:pointer;border:none;background:none}
        .pn-nav-link:hover,.pn-nav-link.active{color:var(--primary);background:var(--primary-light)}
        .pn-nav-link.active{color:var(--primary);background:var(--primary-light)}
        .pn-chevron{width:12px;height:12px;transition:transform 0.2s}
        .pn-nav-item:hover .pn-chevron{transform:rotate(180deg)}
        .pn-dropdown{display:none;position:absolute;top:100%;left:0;min-width:200px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);z-index:999;padding:6px;overflow:hidden}
        .pn-nav-item:hover .pn-dropdown,.pn-nav-item.open .pn-dropdown{display:block}.pn-dropdown::before{content:"";position:absolute;top:-10px;left:0;right:0;height:10px}
        .pn-dropdown a{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;font-weight:500;color:var(--text);text-decoration:none;border-radius:8px;transition:all 0.12s}
        .pn-dropdown a:hover{background:var(--primary-light);color:var(--primary)}
        .pn-dropdown .sep{height:1px;background:var(--border);margin:4px 0}
        .pn-nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0}
        .pn-user-btn{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--primary-light);border:1px solid transparent;border-radius:8px;font-size:13px;font-weight:600;color:var(--primary);cursor:pointer;text-decoration:none;transition:all 0.15s}
        .pn-user-btn:hover{background:var(--primary);color:#fff}
        .pn-avatar{width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
        .pn-hamburger{display:none;background:none;border:1px solid var(--border);border-radius:8px;padding:6px 10px;cursor:pointer;flex-direction:column;gap:4px}
        .pn-hamburger span{display:block;width:18px;height:2px;background:var(--muted);transition:all 0.2s}
        .pn-mobile-menu{display:none;background:var(--card);border-top:1px solid var(--border);padding:12px 0}
        .pn-mobile-menu a{display:block;padding:9px 24px;font-size:13.5px;font-weight:500;color:var(--text);text-decoration:none}
        .pn-mobile-menu a:hover{background:var(--primary-light);color:var(--primary)}
        .pn-mobile-sec{padding:8px 24px 4px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.7px;margin-top:6px}
        @media(max-width:960px){.pn-nav{display:none}.pn-hamburger{display:flex}.pn-mobile-menu.open{display:block}}

        /* ─── LAYOUT ─── */
        .pn-main{max-width:1440px;margin:0 auto;padding:36px 40px;width:100%;flex:1}
        .pn-footer{background:var(--card);border-top:1px solid var(--border);padding:20px 24px;text-align:center;font-size:12.5px;color:var(--muted);margin-top:auto}
        .pn-footer a{color:var(--primary);text-decoration:none}
        .pn-footer a:hover{text-decoration:underline}

        /* ─── CARDS ─── */
        .pn-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px}
        .pn-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
        .pn-card-title{font-size:14px;font-weight:700;color:var(--text)}
        .pn-card-body{padding:28px 32px}
        .pn-card-body-flush{padding:0}

        /* ─── PAGE HEADER ─── */
        .pn-page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap}
        .pn-page-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-0.3px}
        .pn-page-subtitle{font-size:13px;color:var(--muted);margin-top:3px}

        /* ─── BUTTONS ─── */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 18px;font-size:13.5px;font-weight:600;border-radius:var(--radius-sm);border:none;cursor:pointer;text-decoration:none;transition:all 0.15s;line-height:1.3;white-space:nowrap}
        .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-dark);color:#fff}
        .btn-accent{background:var(--accent);color:#fff}.btn-accent:hover{background:var(--accent-dark);color:#fff}
        .btn-outline{background:var(--card);color:var(--primary);border:1.5px solid var(--border)}.btn-outline:hover{border-color:var(--primary);background:var(--primary-light)}
        .btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#dc2626;color:#fff}
        .btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#059669;color:#fff}
        .btn-default{background:var(--card);color:var(--text);border:1.5px solid var(--border)}.btn-default:hover{border-color:var(--muted);color:var(--text)}
        .text-danger{color:var(--danger)}
        .btn-sm{padding:6px 12px;font-size:12.5px}
        .btn-xs{padding:4px 10px;font-size:12px}
        .btn svg,.btn i{flex-shrink:0;line-height:1}

        /* ─── BADGES ─── */
        .badge{display:inline-flex;align-items:center;padding:3px 10px;font-size:11.5px;font-weight:600;border-radius:999px;line-height:1.4}
        .badge-active,.badge-paid{background:#dcfce7;color:#15803d}
        .badge-pending{background:#fef9c3;color:#854d0e}
        .badge-unpaid{background:#fff7ed;color:#c2410c}
        .badge-overdue{background:#fee2e2;color:#991b1b}
        .badge-suspended,.badge-terminated,.badge-cancelled{background:var(--bg);color:var(--muted)}
        .badge-closed{background:var(--bg);color:var(--muted)}
        .badge-open{background:#dbeafe;color:#1d4ed8}
        .badge-answered,.badge-in-progress{background:#e0f2fe;color:#0369a1}
        .badge-customer-reply{background:#fce7f3;color:#be185d}
        .badge-low{background:#f0fdf4;color:#15803d}
        .badge-medium{background:#fff7ed;color:#c2410c}
        .badge-high{background:#fee2e2;color:#991b1b}
        .badge-yes{background:#dcfce7;color:#15803d}
        .badge-no{background:var(--bg);color:var(--muted)}

        /* ─── TABLES ─── */
        .pn-table{width:100%;border-collapse:collapse}
        .pn-table th{padding:11px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;background:var(--bg);border-bottom:1px solid var(--border);text-align:left;white-space:nowrap}
        .pn-table td{padding:13px 16px;font-size:13.5px;color:var(--text);border-bottom:1px solid var(--border);vertical-align:middle}
        .pn-table tbody tr:last-child td{border-bottom:none}
        .pn-table tbody tr:hover td{background:var(--bg)}
        .pn-table a{color:var(--primary);text-decoration:none;font-weight:500}
        .pn-table a:hover{text-decoration:underline}

        /* ─── FORMS ─── */
        .form-group{margin-bottom:20px}
        .form-label,.pn-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
        .form-label .req{color:var(--danger)}
        /* .pn-input and .pn-label ride the same definitions: four screens - the
           bank-transfer notification, payment methods, two-factor setup, quotes -
           used them while nothing defined them, so their inputs rendered with no
           border and no background: invisible fields on a white card. */
        .form-control,.pn-input{width:100%;padding:9px 13px;font-size:13.5px;color:var(--text);background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-sm);transition:border-color 0.15s,box-shadow 0.15s;outline:none;font-family:inherit}
        .form-control:focus,.pn-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,77,128,0.1)}
        textarea.form-control,textarea.pn-input{resize:vertical;min-height:120px;line-height:1.6}
        select.form-control{cursor:pointer}
        .form-hint{font-size:12px;color:var(--muted);margin-top:4px}
        .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media(max-width:600px){.form-grid-2{grid-template-columns:1fr}}

        /* ─── ALERTS ─── */
        .pn-alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:13.5px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;border:1px solid transparent}
        .pn-alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
        .pn-alert-error{background:#fef2f2;border-color:#fecaca;color:#991b1b}
        .pn-alert-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
        .pn-alert-info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
        .pn-alert-warning{background:#fffbeb;border-color:#fde68a;color:#92400e}
        .pn-alert ul{padding-left:16px;margin:4px 0 0}
        .pn-alert ul li{margin-bottom:2px}

        /* ─── STAT CARDS ─── */
 .pn-stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px}
 @media(max-width:1000px){.pn-stat-grid{grid-template-columns:repeat(3,1fr)}}
 @media(max-width:640px){.pn-stat-grid{grid-template-columns:repeat(2,1fr)}}
        .pn-stat{display:flex;flex-direction:column;padding:20px 24px;cursor:pointer;text-decoration:none;color:inherit;transition:box-shadow 0.15s}
        .pn-stat:hover{box-shadow:var(--shadow-md)}
        .pn-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;flex-shrink:0}
        .pn-stat-icon svg{width:22px;height:22px}
        .pn-stat-val{font-size:28px;font-weight:800;color:var(--text);letter-spacing:-0.5px;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pn-stat-lbl{font-size:12.5px;font-weight:500;color:var(--muted);margin-top:5px}
        .pn-stat-icon-blue{background:#dbeafe;color:#1d4ed8}
        .pn-stat-icon-green{background:#dcfce7;color:#15803d}
        .pn-stat-icon-orange{background:#fff7ed;color:#c2410c}
        .pn-stat-icon-red{background:#fee2e2;color:#991b1b}

        /* ─── TWO COL ─── */
        .pn-2col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
        @media(max-width:768px){.pn-2col{grid-template-columns:1fr}}

        /* ─── PROGRESS BARS ─── */
        .pn-progress-wrap{background:#e2e8f0;border-radius:999px;height:8px;overflow:hidden}
        .pn-progress-fill{height:100%;border-radius:999px;transition:width 0.4s}

        /* ─── EMPTY STATE ─── */
        .pn-empty{text-align:center;padding:56px 24px;color:var(--muted)}
        .pn-empty-icon{font-size:40px;margin-bottom:14px;opacity:0.5}
        .pn-empty p{font-size:14px;margin-bottom:16px}

        /* ─── BREADCRUMB ─── */
        .pn-back{display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-size:13px;font-weight:500;text-decoration:none;margin-bottom:20px}
        .pn-back:hover{text-decoration:underline}
        .pn-back svg{width:14px;height:14px}

        /* ─── QUICK ACTIONS ─── */
        .pn-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}

        /* ─── DETAIL LIST ─── */
        .pn-detail-list{list-style:none}
        .pn-detail-list li{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13.5px}
        .pn-detail-list li:last-child{border-bottom:none}
        .pn-detail-list .key{color:var(--muted);font-weight:500}
        .pn-detail-list .val{font-weight:600;color:var(--text);text-align:right}

        /* ─── TICKET MESSAGES ─── */
        .pn-msg{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px;overflow:hidden}
        .pn-msg.staff{border-left:3px solid var(--primary)}
        .pn-msg-head{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
        .pn-msg.staff .pn-msg-head{background:#eff6ff}
        .pn-msg-author{font-size:13px;font-weight:700;color:var(--text)}
        .pn-msg.staff .pn-msg-author{color:var(--primary)}
        .pn-msg-date{font-size:12px;color:var(--muted)}
        .pn-msg-body{padding:16px 18px;font-size:13.5px;line-height:1.75;color:var(--text);white-space:pre-wrap}

        /* ─── GATEWAY TABS ─── */
        .gw-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
        .gw-tab{padding:8px 18px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;font-weight:600;color:var(--muted);background:var(--card);transition:all 0.15s}
        .gw-tab:hover{border-color:var(--primary);color:var(--primary)}
        .gw-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
        .gw-panel{display:none}.gw-panel.active{display:block}
        .gw-form-box{background:var(--bg);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:18px}

        /* ─── PRODUCT CARDS ─── */
        .pn-product-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
        @media(max-width:900px){.pn-product-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:560px){.pn-product-grid{grid-template-columns:1fr}}
        .pn-product-card{display:flex;flex-direction:column;padding:24px;transition:all 0.2s;position:relative}
        .pn-product-card:hover{box-shadow:var(--shadow-md)}
        .pn-product-card.featured{border-color:var(--primary)}
        .pn-product-price{font-size:26px;font-weight:800;color:var(--text);letter-spacing:-0.5px;margin:8px 0 4px}
        .pn-product-price .cycle{font-size:13px;font-weight:400;color:var(--muted)}
        .pn-popular{position:absolute;top:-1px;right:18px;background:var(--accent);color:#fff;font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:0 0 8px 8px;letter-spacing:0.3px;text-transform:uppercase}

        /* ─── CHECKOUT ─── */
        .pn-checkout-grid{display:grid;grid-template-columns:1fr 320px;gap:24px}
        @media(max-width:900px){.pn-checkout-grid{grid-template-columns:1fr}}
        .pn-pay-option{border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:13px 16px;cursor:pointer;margin-bottom:8px;display:flex;align-items:center;gap:12px;transition:all 0.15s}
        .pn-pay-option:hover{border-color:var(--primary);background:var(--primary-light)}
        .pn-pay-option.selected{border-color:var(--primary);background:var(--primary-light)}
        .pn-pay-option input[type=radio]{margin:0;accent-color:var(--primary)}

        /* ─── AMOUNT PRESETS ─── */
        .pn-amount-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
        .pn-amount-btn{padding:10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:700;color:var(--text);background:var(--card);cursor:pointer;text-align:center;transition:all 0.15s}
        .pn-amount-btn:hover,.pn-amount-btn.selected{border-color:var(--primary);background:var(--primary-light);color:var(--primary)}

        /* ─── AFFILIATE ─── */
        .pn-aff-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        @media(max-width:768px){.pn-aff-grid{grid-template-columns:repeat(2,1fr)}}
        .pn-aff-stat{text-align:center;padding:20px}
        .pn-aff-val{font-size:28px;font-weight:800;color:var(--primary);letter-spacing:-0.5px}
        .pn-aff-lbl{font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:6px}

        /* ─── WELCOME BANNER ─── */
        .pn-welcome{background:linear-gradient(135deg,var(--primary) 0%,#1e5fa0 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
        .pn-welcome-text h2{font-size:20px;font-weight:700;color:#fff;letter-spacing:-0.3px}
        .pn-welcome-text p{font-size:13.5px;color:rgba(255,255,255,0.75);margin-top:4px}
        .pn-welcome-credit{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:var(--radius-sm);padding:12px 18px;text-align:right}
        .pn-welcome-credit .credit-lbl{font-size:11px;color:rgba(255,255,255,0.65);font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
        .pn-welcome-credit .credit-val{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px}

        /* ─── CART ─── */
        .pn-cart-grid{display:grid;grid-template-columns:1fr 300px;gap:20px}
        @media(max-width:900px){.pn-cart-grid{grid-template-columns:1fr}}
        .pn-order-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13.5px;border-bottom:1px solid var(--border)}
        .pn-order-row:last-child{border-bottom:none;font-weight:700;font-size:15px;padding-top:12px}
        .pn-order-row .key{color:var(--muted)}

        /* ─── MISC ─── */
        .pn-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:12px}
        .pn-code{background:var(--bg);padding:3px 8px;border-radius:6px;font-family:monospace;font-size:12.5px;color:var(--text)}
        .link{color:var(--primary);text-decoration:none;font-weight:500}
        .link:hover{text-decoration:underline}
        .text-muted{color:var(--muted)}
        .text-sm{font-size:12.5px}
        .mt-16{margin-top:16px}
        .mt-24{margin-top:24px}
        .mb-16{margin-bottom:16px}
        .mb-24{margin-bottom:24px}
        .flex{display:flex}
        .items-center{align-items:center}
        .gap-8{gap:8px}
        .gap-12{gap:12px}
        .gap-16{gap:16px}
        .w-full{width:100%}
        code{font-family:monospace}
    
        /* ===== DARK MODE ===== */
        :root[data-theme="dark"] {
            --bg:#0f172a; --card:#1e293b; --border:#334155;
            --text:#e2e8f0; --muted:#94a3b8;
        }
        :root[data-theme="dark"] body { background:var(--bg) !important; color:var(--text) !important; }
        :root[data-theme="dark"] .pn-navbar { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-card { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-table th { background:#1e293b !important; }
        :root[data-theme="dark"] .pn-table td { border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-table tbody tr:hover td { background:#334155 !important; }
        :root[data-theme="dark"] .form-control, :root[data-theme="dark"] .pn-input { background:#1e293b !important; border-color:#475569 !important; color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-footer { background:#1e293b !important; border-color:var(--border) !important; color:var(--muted) !important; }
        :root[data-theme="dark"] .pn-dropdown { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-dropdown a { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-dropdown a:hover { background:#334155 !important; }
        :root[data-theme="dark"] .pn-card-title { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-page-title { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-stat-val { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-detail-list .val { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-detail-list li { border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-msg { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-msg-head { background:#0f172a !important; }
        :root[data-theme="dark"] .pn-msg-body { color:#cbd5e1 !important; }
        :root[data-theme="dark"] .pn-alert-info { background:#1e3a5f !important; border-color:#2563eb !important; }
        :root[data-theme="dark"] .pn-alert-success { background:#14532d !important; border-color:#16a34a !important; }
        :root[data-theme="dark"] .pn-alert-warning { background:#451a03 !important; border-color:#d97706 !important; }
        :root[data-theme="dark"] .pn-alert-error, :root[data-theme="dark"] .pn-alert-danger { background:#450a0a !important; border-color:#dc2626 !important; }
        /* Badges carry fixed light pastels; in the dark they turned into pale
           chips with unreadable text. Deep tones, same meaning. */
        :root[data-theme="dark"] .badge-active, :root[data-theme="dark"] .badge-paid { background:#14532d !important; color:#86efac !important; }
        :root[data-theme="dark"] .badge-pending { background:#422006 !important; color:#fde047 !important; }
        :root[data-theme="dark"] .badge-unpaid { background:#431407 !important; color:#fdba74 !important; }
        :root[data-theme="dark"] .badge-overdue { background:#450a0a !important; color:#fca5a5 !important; }
        :root[data-theme="dark"] .badge-suspended, :root[data-theme="dark"] .badge-terminated,
        :root[data-theme="dark"] .badge-cancelled, :root[data-theme="dark"] .badge-closed { background:#1e293b !important; color:var(--muted) !important; }
    </style>
    @yield("styles")

    @if(($textDirection ?? 'ltr') === 'rtl')
    <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
</head>
<body>
@if(session('impersonating_admin_id'))
<div style="background:#f59e0b;color:#000;padding:8px 16px;text-align:center;font-weight:600;font-size:13px;position:sticky;top:0;z-index:9999;">
    {{ __("client.impersonation.viewing_as") }} {{ auth()->user()->full_name ?? auth()->user()->email }}.
    <a href="{{ route('admin.clients.stop-impersonation') }}" style="color:#000;text-decoration:underline;margin-left:10px;">
        &larr; {{ __("client.impersonation.return_to_admin") }}
    </a>
</div>
@endif

<nav class="pn-navbar">
    <div class="pn-navbar-inner">
        <a href="{{ route("client.home") }}" class="pn-brand">@if(!empty($customLogo))<img src="{{ $customLogo }}" alt="Logo" style="max-height:32px;">@else {{ company_name() }} <span class="pn-brand-dot"></span>@endif</a>

        <div class="pn-nav">
            <div class="pn-nav-item">
                <a href="{{ route("client.home") }}" class="pn-nav-link {{ request()->routeIs("client.home") ? "active" : "" }}">{{ __('client.nav.dashboard') }}</a>
            </div>
            <div class="pn-nav-item">
                <button type="button" class="pn-nav-link {{ request()->routeIs("client.services.*") || request()->routeIs("client.store*") ? "active" : "" }}">{{ __('client.nav.services') }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="pn-dropdown">
                    <a href="{{ route("client.services.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                        {{ __('client.nav.my_services') }}
                    </a>
                    <a href="{{ route("client.store") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('client.nav.order_new_service') }}
                    </a>
                </div>
            </div>
            {{-- A guest's menu mirrors the storefront: one link per product
                 group, straight from the catalogue - the same source the
                 marketing pages read, so the menus cannot drift apart. --}}
            @guest
            @php $navGroups = \App\Models\ProductGroup::where('hidden', 0)->orderBy('sort_order')->get(); @endphp
            @foreach($navGroups as $navGroup)
            <div class="pn-nav-item">
                <a href="{{ route("client.store") }}?kategori={{ $navGroup->slug }}"
                   class="pn-nav-link {{ request()->routeIs("client.store*") && request("kategori") === $navGroup->slug ? "active" : "" }}">
                    {{ $navGroup->name }}
                </a>
            </div>
            @endforeach
            @endguest
            <div class="pn-nav-item">
                <button type="button" class="pn-nav-link {{ request()->routeIs("client.domains.*") ? "active" : "" }}">{{ __('client.nav.domains') }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="pn-dropdown">
                    <a href="{{ route("client.domains.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        {{ __('client.nav.my_domains') }}
                    </a>
                    <a href="/"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> {{ __('client.nav.home_site') }}</a>
                    <a href="/client/domain-search"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35"/></svg> {{ __('client.nav.register_domain') }}</a>
                    <a href="/client/domain-pricing"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg> {{ __('client.nav.domain_pricing') }}</a>
                </div>
            </div>
            <div class="pn-nav-item">
                <a href="{{ route("client.ssl.index") }}" class="pn-nav-link {{ request()->routeIs("client.ssl.*") ? "active" : "" }}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>{{ __('client.nav.ssl') }}
                </a>
            </div>
            <div class="pn-nav-item">
                <button type="button" class="pn-nav-link {{ request()->routeIs("client.invoices.*") || request()->routeIs("client.funds.*") || request()->routeIs("client.quotes.*") || request()->routeIs("client.payment-methods.*") || request()->routeIs("client.emails.*") ? "active" : "" }}">{{ __('client.nav.billing') }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="pn-dropdown">
                    <a href="{{ route("client.invoices.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ __('client.nav.invoices') }}
                    </a>
                    <a href="{{ route("client.funds.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('client.nav.add_funds') }}
                    </a>
                    <div class="sep"></div>
                    <a href="{{ route("client.quotes.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        {{ __('client.nav.quotes') }}
                    </a>
                    <a href="{{ route("client.payment-methods.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        {{ __('client.nav.payment_methods') }}
                    </a>
                    <a href="{{ route("client.emails.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ __('client.nav.email_history') }}
                    </a>
                </div>
            </div>
            <div class="pn-nav-item">
                <button type="button" class="pn-nav-link {{ request()->routeIs("client.tickets.*") || request()->routeIs("client.kb.*") || request()->routeIs("client.announcements.*") || request()->routeIs("client.contact") || request()->routeIs("client.downloads.*") ? "active" : "" }}">{{ __('client.nav.support') }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="pn-dropdown">
                    <a href="{{ route("client.tickets.create") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('client.nav.open_ticket') }}
                    </a>
                    <a href="{{ route("client.tickets.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        {{ __('client.nav.my_tickets') }}
                    </a>
                    <div class="sep"></div>
                    <a href="{{ route("client.kb.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        {{ __('client.nav.knowledge_base') }}
                    </a>
                    <a href="{{ route("client.announcements.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        {{ __('client.nav.announcements') }}
                    </a>
                    <a href="{{ route("client.network-status") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                        {{ __('client.nav.network_status') }}
                    </a>
                </div>
            </div>
            <div class="pn-nav-item">
                <button type="button" class="pn-nav-link {{ request()->routeIs("client.account.*") ? "active" : "" }}">{{ __('client.nav.account') }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="pn-dropdown">
                    <a href="{{ route("client.account.profile") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ __('client.nav.edit_profile') }}
                    </a>
                    <a href="{{ route("client.account.password") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ __('client.nav.change_password') }}
                    </a>
                    <a href="{{ route("client.account.contacts") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ __('client.nav.contacts') }}
                    </a>
                    <a href="{{ route("client.account.security") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        {{ __('client.nav.security') }}
                    </a>
                    <div class="sep"></div>
                    <a href="{{ route("client.affiliates.index") }}">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        {{ __('client.nav.affiliates') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="pn-nav-right">
        @include("client.layouts.partials.language-selector")
                @if($darkModeEnabled ?? false)
                <button onclick="toggleDarkMode()" class="dark-toggle" title="{{ __('client.toggle_dark_mode') }}" style="background:none;border:1px solid var(--border);border-radius:8px;padding:6px 10px;cursor:pointer;color:var(--muted);font-size:16px;transition:all 0.15s;">
                    <i class="ri-sun-line" id="lightIcon" style="display:none;"></i>
                    <i class="ri-moon-line" id="darkIcon"></i>
                </button>
                @endif
            @auth
            <div class="pn-nav-item">
                <a class="pn-user-btn pn-nav-link">
                    <div class="pn-avatar">{{ strtoupper(substr(auth()->user()->first_name ?? "U", 0, 1)) }}</div>
                    {{ auth()->user()->first_name }}
                    <svg class="pn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="pn-dropdown" style="right:0;left:auto;min-width:180px;">
                    <a href="{{ route("client.account.profile") }}">{{ __('client.nav.my_profile') }}</a>
                    <div class="sep"></div>
                    <a href="{{ route("client.funds.index") }}">{{ __('client.nav.add_funds') }}</a>
                    <div class="sep"></div>
                    <form method="POST" action="{{ route("client.logout") }}" style="padding:0;">
                        @csrf
                        <button type="submit" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;font-size:13px;font-weight:500;color:#ef4444;background:none;border:none;cursor:pointer;border-radius:8px;text-align:left;font-family:inherit;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            {{ __('client.nav.sign_out') }}
                        </button>
                    </form>
                </div>
            </div>
            @else
                <a href="{{ route("client.login") }}" class="btn btn-outline btn-sm">{{ __('common.actions.login') }}</a>
                <a href="{{ route("client.register") }}" class="btn btn-primary btn-sm">{{ __('client.nav.get_started') }}</a>
            @endauth
        </div>

        <button class="pn-hamburger" onclick="document.getElementById('pnMobileMenu').classList.toggle('open')">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div id="pnMobileMenu" class="pn-mobile-menu">
        <a href="{{ route("client.home") }}">{{ __('client.nav.dashboard') }}</a>
        <div class="pn-mobile-sec">{{ __('client.nav.services') }}</div>
        <a href="{{ route("client.services.index") }}" style="padding-left:36px">{{ __('client.nav.my_services') }}</a>
        <a href="{{ route("client.store") }}" style="padding-left:36px">{{ __('client.nav.order_new') }}</a>
        <div class="pn-mobile-sec">{{ __('client.nav.billing') }}</div>
        <a href="{{ route("client.invoices.index") }}" style="padding-left:36px">{{ __('client.nav.invoices') }}</a>
        <a href="{{ route("client.funds.index") }}" style="padding-left:36px">{{ __('client.nav.add_funds') }}</a>
        <div class="pn-mobile-sec">{{ __('client.nav.support') }}</div>
        <a href="{{ route("client.tickets.create") }}" style="padding-left:36px">{{ __('client.nav.open_ticket') }}</a>
        <a href="{{ route("client.tickets.index") }}" style="padding-left:36px">{{ __('client.nav.my_tickets') }}</a>
        <a href="{{ route("client.kb.index") }}" style="padding-left:36px">{{ __('client.nav.knowledge_base') }}</a>
        <a href="{{ route("client.announcements.index") }}" style="padding-left:36px">{{ __('client.nav.announcements') }}</a>
        <div class="pn-mobile-sec">{{ __('client.nav.account') }}</div>
        <a href="{{ route("client.account.profile") }}" style="padding-left:36px">{{ __('client.nav.profile') }}</a>
        <a href="{{ route("client.account.password") }}" style="padding-left:36px">{{ __('client.nav.password') }}</a>
        <a href="{{ route("client.account.contacts") }}" style="padding-left:36px">{{ __('client.nav.contacts') }}</a>
        @auth
        <div style="padding:12px 24px 8px;">
            <form method="POST" action="{{ route("client.logout") }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center;">{{ __('client.nav.sign_out') }}</button>
            </form>
        </div>
        @endauth
    </div>
</nav>

<div class="pn-main">
    @if(session("success"))
        <div class="pn-alert pn-alert-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("success") }}
        </div>
    @endif
    @if(session("error"))
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("error") }}
        </div>
    @endif
    {{-- Validation errors. Only the flash keys were drawn here, so every
         refused form (the free-plan limit, the cycle guard, checkout rules)
         bounced back to the same page with no explanation at all. --}}
    @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                @foreach($errors->all() as $validationError)
                    <div>{{ $validationError }}</div>
                @endforeach
            </div>
        </div>
    @endif
    @if(session("info"))
        <div class="pn-alert pn-alert-info">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("info") }}
        </div>
    @endif
    @if(session("warning"))
        <div class="pn-alert pn-alert-warning">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            {{ session("warning") }}
        </div>
    @endif

    @yield("content")
</div>

<footer class="pn-footer">
    <span>&copy; {{ date("Y") }} {{ company_name() }}. {{ __('client.footer.all_rights_reserved') }}</span>
    &nbsp;&middot;&nbsp;
    <a href="{{ route("client.contact") }}">{{ __('client.nav.contact') }}</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route("client.announcements.index") }}">{{ __('client.nav.announcements') }}</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route("client.kb.index") }}">{{ __('client.nav.knowledge_base') }}</a>
</footer>

@yield("scripts")
{{-- A page may push instead of yielding; without this the block is silently discarded. --}}
@stack('scripts')
<script>
// Dropdown click toggle for mobile/touch
document.querySelectorAll(".pn-nav-item > .pn-nav-link, .pn-nav-item > button.pn-nav-link").forEach(function(link) {
    link.addEventListener("click", function(e) {
        var item = this.closest(".pn-nav-item");
        if (item.querySelector(".pn-dropdown")) {
            e.preventDefault();
            // Close others
            document.querySelectorAll(".pn-nav-item.open").forEach(function(el) {
                if (el !== item) el.classList.remove("open");
            });
            item.classList.toggle("open");
        }
    });
});
// Close dropdowns when clicking outside
document.addEventListener("click", function(e) {
    if (!e.target.closest(".pn-nav-item")) {
        document.querySelectorAll(".pn-nav-item.open").forEach(function(el) { el.classList.remove("open"); });
    }
});
</script>

    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <script>
    function toggleDarkMode(){var d=document.documentElement.getAttribute('data-theme')==='dark';var n=d?'light':'dark';document.documentElement.setAttribute('data-theme',n);document.cookie='pnlcs_theme='+n+';path=/;max-age=31536000;SameSite=Lax';var li=document.getElementById('lightIcon'),di=document.getElementById('darkIcon');if(li&&di){li.style.display=d?'':'none';di.style.display=d?'none':'';}}
    (function(){var m=document.cookie.match(/pnlcs_theme=(\w+)/);if(m&&m[1]==='dark'){document.documentElement.setAttribute('data-theme','dark');var li=document.getElementById('lightIcon'),di=document.getElementById('darkIcon');if(li)li.style.display='';if(di)di.style.display='none';}})();
    </script>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('form').forEach(function(f){
        f.addEventListener('submit',function(){
            var btn=f.querySelector('button[type=submit],input[type=submit]');
            if(btn&&!btn.disabled){btn.disabled=true;btn.classList.add('submitting');
            setTimeout(function(){btn.disabled=false;btn.classList.remove('submitting');},5000);}
        });
    });
});
</script>
