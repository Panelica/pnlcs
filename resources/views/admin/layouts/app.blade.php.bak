<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Admin") - PNLCS</title>
    @vite(["resources/css/app.css"])
    <style>
    [x-cloak] { display: none !important; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; background: #f6f6f6; color: #333; }

    /* ── TOP NAV ── */
    #top-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 45px; background: #1a4d80; display: flex; align-items: center; padding: 0 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
    .nav-logo { color: #fff; font-size: 17px; font-weight: 700; text-decoration: none; margin-right: 16px; white-space: nowrap; }
    .nav-logo span { color: #7eb8f7; }
    .top-nav-menus { display: flex; align-items: stretch; height: 45px; flex: 1; }
    .nav-dd { position: relative; }
    .nav-dd > a { display: flex; align-items: center; height: 45px; padding: 0 11px; color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: background 0.1s; gap: 4px; }
    .nav-dd > a:hover { background: #2f5b88; color: #fff; }
    .nav-dd > a.active { background: rgba(255,255,255,0.12); }
    .nav-dd > a .arr { font-size: 9px; opacity: 0.6; }
    .dd-menu { display: none; position: absolute; top: 45px; left: 0; min-width: 200px; background: #fff; border: 1px solid #d0d0d0; border-top: 3px solid #1a4d80; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; }
    .nav-dd:hover .dd-menu { display: block; }
    .dd-menu a { display: block; padding: 7px 14px; color: #333; font-size: 13px; text-decoration: none; }
    .dd-menu a:hover { background: #f0f4f8; color: #1a4d80; }
    .dd-menu .sep { height: 1px; background: #e5e5e5; margin: 3px 0; }
    .dd-menu .hdr { padding: 6px 14px 3px; font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
    .nav-right { display: flex; align-items: stretch; height: 45px; margin-left: auto; }
    .nav-right .nav-dd > a { color: rgba(255,255,255,0.9); }
    .nav-right .nav-dd > a:hover { background: #2f5b88; }
    .nav-right .nav-dd .dd-menu { right: 0; left: auto; }
    .nav-avatar { width: 26px; height: 26px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; }
    .mode-btn { background: none; border: 1px solid rgba(255,255,255,0.25); color: rgba(255,255,255,0.8); cursor: pointer; padding: 3px 8px; font-size: 11px; border-radius: 3px; margin: 0 4px; }
    .mode-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
    .hamburger { display: none; background: none; border: none; color: #fff; cursor: pointer; padding: 4px 8px; font-size: 18px; }

    /* ── SIDEBAR (full nav mode) ── */
    #full-sidebar { width: 220px; position: fixed; top: 45px; left: 0; bottom: 0; background: #1e2a3a; overflow-y: auto; z-index: 900; display: none; }
    .sb-section { padding: 8px 0 4px; }
    .sb-hdr { padding: 8px 16px 4px; font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; }
    .sb-link { display: flex; align-items: center; gap: 8px; padding: 7px 16px; color: rgba(255,255,255,0.7); font-size: 13px; text-decoration: none; transition: all 0.1s; border-left: 3px solid transparent; }
    .sb-link:hover { color: #fff; background: rgba(255,255,255,0.06); border-left-color: #337ab7; }
    .sb-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left-color: #337ab7; font-weight: 600; }
    .sb-link .ico { width: 16px; text-align: center; font-size: 13px; opacity: 0.7; }
    .sb-sep { height: 1px; background: rgba(255,255,255,0.08); margin: 4px 16px; }

    /* ── CONTEXT SIDEBAR (navbar mode) ── */
    #ctx-sidebar { width: 200px; position: fixed; top: 45px; left: 0; bottom: 0; background: #f4f4f4; border-right: 1px solid #ddd; overflow-y: auto; z-index: 800; }
    .cs-section { padding: 8px 0 4px; }
    .cs-hdr { padding: 6px 14px 4px; font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e0e0e0; margin-bottom: 2px; }
    .cs-link { display: flex; align-items: center; gap: 7px; padding: 6px 14px; color: #555; font-size: 13px; text-decoration: none; transition: all 0.1s; border-left: 3px solid transparent; }
    .cs-link:hover { color: #1a4d80; background: #eaeef2; border-left-color: #1a4d80; }
    .cs-link.active { color: #1a4d80; background: #dce8f5; border-left-color: #1a4d80; font-weight: 600; }
    .cs-link .ico { width: 14px; text-align: center; opacity: 0.6; font-size: 12px; }

    /* ── CONTENT ── */
    #content-area { margin-left: 200px; padding: 20px; min-height: calc(100vh - 45px); }

    /* ── LAYOUT MODES ── */
    body.mode-sidebar #full-sidebar { display: block; }
    body.mode-sidebar #ctx-sidebar { display: none; }
    body.mode-sidebar .top-nav-menus { display: none; }
    body.mode-sidebar #content-area { margin-left: 220px; }
    body.mode-navbar #full-sidebar { display: none; }
    body.mode-navbar #ctx-sidebar { display: block; }
    body.mode-navbar .top-nav-menus { display: flex; }
    body.mode-navbar #content-area { margin-left: 200px; }

    /* Flash */
    .flash { padding: 10px 16px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; border: 1px solid transparent; }
    .flash-ok { background: #dff0d8; color: #3c763d; border-color: #d6e9c6; }
    .flash-err { background: #f2dede; color: #a94442; border-color: #ebccd1; }
    .flash-warn { background: #fcf8e3; color: #8a6d3b; border-color: #faebcc; }
    .flash-info { background: #d9edf7; color: #31708f; border-color: #bce8f1; }

    /* Mobile */
    @media (max-width: 1023px) {
        .hamburger { display: block; }
        .top-nav-menus { display: none !important; }
        #ctx-sidebar, #full-sidebar { transform: translateX(-100%); transition: transform 0.2s; }
        #ctx-sidebar.open, #full-sidebar.open { transform: translateX(0); }
        #content-area { margin-left: 0 !important; padding: 12px; }
        #mob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 750; }
        #mob-overlay.show { display: block; }
    }
    @media (min-width: 1024px) { #mob-overlay { display: none !important; } }
    </style>
</head>
<body class="mode-navbar">

{{-- ── TOP NAV ── --}}
<nav id="top-nav">
    <button class="hamburger" onclick="toggleMobile()">&#9776;</button>
    <a href="{{ route("admin.dashboard") }}" class="nav-logo">PNL<span>CS</span></a>

    {{-- Center menus (navbar mode only) --}}
    <div class="top-nav-menus">
        <div class="nav-dd"><a href="#"><b style="font-size:15px;margin-right:2px;">+</b> New <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.clients.create") }}">New Client</a>
                <a href="{{ route("admin.invoices.create") }}">New Invoice</a>
                <a href="{{ route("admin.quotes.create") }}">New Quote</a>
                <a href="{{ route("admin.tickets.index") }}">New Ticket</a>
            </div>
        </div>
        <div class="nav-dd"><a href="{{ route("admin.clients.index") }}" class="{{ request()->routeIs("admin.clients.*") ? "active" : "" }}">Clients <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.clients.index") }}">View Clients</a>
                <a href="{{ route("admin.clients.create") }}">Add New</a>
                <div class="sep"></div>
                <a href="{{ route("admin.services.index") }}">Products/Services</a>
                <a href="{{ route("admin.domains.index") }}">Domains</a>
            </div>
        </div>
        <div class="nav-dd"><a href="{{ route("admin.orders.index") }}" class="{{ request()->routeIs("admin.orders.*") ? "active" : "" }}">Orders <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.orders.index") }}">All Orders</a>
                <a href="{{ route("admin.orders.index") }}?status=pending">Pending</a>
                <a href="{{ route("admin.orders.index") }}?status=active">Active</a>
                <a href="{{ route("admin.orders.index") }}?status=fraud">Fraud</a>
            </div>
        </div>
        <div class="nav-dd"><a href="{{ route("admin.invoices.index") }}" class="{{ request()->routeIs("admin.invoices.*") || request()->routeIs("admin.config.transactions") ? "active" : "" }}">Billing <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.invoices.index") }}">Invoices</a>
                <a href="{{ route("admin.config.transactions") }}">Transactions</a>
                <a href="{{ route("admin.quotes.index") }}">Quotes</a>
                <a href="{{ route("admin.config.billable-items") }}">Billable Items</a>
            </div>
        </div>
        <div class="nav-dd"><a href="{{ route("admin.tickets.index") }}" class="{{ request()->routeIs("admin.tickets.*") ? "active" : "" }}">Support <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.tickets.index") }}">Tickets</a>
                <div class="sep"></div>
                <a href="{{ route("admin.config.announcements") }}">Announcements</a>
                <a href="{{ route("admin.config.knowledge-base") }}">Knowledge Base</a>
                <a href="{{ route("admin.config.network-issues") }}">Network Issues</a>
                <a href="{{ route("admin.config.downloads") }}">Downloads</a>
            </div>
        </div>
        <div class="nav-dd"><a href="{{ route("admin.reports.index") }}" class="{{ request()->routeIs("admin.reports.*") ? "active" : "" }}">Reports</a></div>
        <div class="nav-dd"><a href="#" class="{{ request()->routeIs("admin.config.todo") || request()->routeIs("admin.config.activity-log") ? "active" : "" }}">Utilities <span class="arr">&#9660;</span></a>
            <div class="dd-menu">
                <a href="{{ route("admin.config.todo") }}">To-Do List</a>
                <a href="{{ route("admin.config.activity-log") }}">Activity Log</a>
                <a href="{{ route("admin.logs.index") }}">System Logs</a>
            </div>
        </div>
    </div>

    <div class="nav-right">
        <button class="mode-btn" onclick="toggleLayoutMode()" title="Switch between Navbar and Sidebar layout">
            <span id="mode-icon">&#9776;</span> <span id="mode-label">Sidebar</span>
        </button>
        <div class="nav-dd"><a href="#" title="Setup"><span style="font-size:14px;">&#9881;</span> <span class="arr">&#9660;</span></a>
            <div class="dd-menu" style="min-width:220px;">
                <div class="hdr">Staff & Security</div>
                <a href="{{ route("admin.config.admins") }}">Admin Staff</a>
                <a href="{{ route("admin.config.admin-roles") }}">Admin Roles</a>
                <a href="{{ route("admin.config.api-credentials") }}">API Credentials</a>
                <div class="sep"></div>
                <div class="hdr">Payments</div>
                <a href="{{ route("admin.config.currencies") }}">Currencies</a>
                <a href="{{ route("admin.config.tax") }}">Tax Rates</a>
                <a href="{{ route("admin.config.gateways") }}">Gateways</a>
                <div class="sep"></div>
                <div class="hdr">Products</div>
                <a href="{{ route("admin.config.servers") }}">Servers</a>
                <a href="{{ route("admin.config.domain-pricing") }}">Domain Pricing</a>
                <a href="{{ route("admin.config.registrars") }}">Registrars</a>
                <div class="sep"></div>
                <div class="hdr">Support</div>
                <a href="{{ route("admin.config.ticket-departments") }}">Departments</a>
                <a href="{{ route("admin.config.ticket-statuses") }}">Statuses</a>
                <a href="{{ route("admin.config.email-templates") }}">Email Templates</a>
                <div class="sep"></div>
                <div class="hdr">System</div>
                <a href="{{ route("admin.settings.general") }}">General Settings</a>
                <a href="{{ route("admin.config.system-database") }}">System Info</a>
            </div>
        </div>
        <div class="nav-dd"><a href="#">
            <div class="nav-avatar">{{ strtoupper(substr(auth("admin")->user()->full_name ?? "A", 0, 1)) }}</div>
            <span style="max-width:100px;overflow:hidden;text-overflow:ellipsis;">{{ auth("admin")->user()->full_name ?? "Admin" }}</span> <span class="arr">&#9660;</span>
        </a>
            <div class="dd-menu">
                <a href="{{ route("admin.config.admins") }}">My Account</a>
                <a href="/" target="_blank">Client Area</a>
                <div class="sep"></div>
                <a href="#" onclick="event.preventDefault();document.getElementById('logout-f').submit();">Logout</a>
                <form id="logout-f" action="{{ route("admin.logout") }}" method="POST" style="display:none;">@csrf</form>
            </div>
        </div>
    </div>
</nav>

<div id="mob-overlay" onclick="toggleMobile()"></div>

<div style="padding-top:45px;display:flex;min-height:100vh;">

{{-- ── FULL SIDEBAR (sidebar mode) ── --}}
<aside id="full-sidebar">
    <div class="sb-section">
        <div class="sb-hdr">Main</div>
        <a href="{{ route("admin.dashboard") }}" class="sb-link {{ request()->routeIs("admin.dashboard") ? "active" : "" }}"><span class="ico">&#127968;</span> Dashboard</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Clients</div>
        <a href="{{ route("admin.clients.index") }}" class="sb-link {{ request()->routeIs("admin.clients.index") ? "active" : "" }}"><span class="ico">&#128100;</span> View Clients</a>
        <a href="{{ route("admin.clients.create") }}" class="sb-link {{ request()->routeIs("admin.clients.create") ? "active" : "" }}"><span class="ico">&#43;</span> Add Client</a>
        <a href="{{ route("admin.services.index") }}" class="sb-link {{ request()->routeIs("admin.services.*") ? "active" : "" }}"><span class="ico">&#128230;</span> Products/Services</a>
        <a href="{{ route("admin.domains.index") }}" class="sb-link {{ request()->routeIs("admin.domains.*") ? "active" : "" }}"><span class="ico">&#127758;</span> Domains</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Orders</div>
        <a href="{{ route("admin.orders.index") }}" class="sb-link {{ request()->routeIs("admin.orders.*") ? "active" : "" }}"><span class="ico">&#128722;</span> All Orders</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Billing</div>
        <a href="{{ route("admin.invoices.index") }}" class="sb-link {{ request()->routeIs("admin.invoices.*") ? "active" : "" }}"><span class="ico">&#128196;</span> Invoices</a>
        <a href="{{ route("admin.config.transactions") }}" class="sb-link {{ request()->routeIs("admin.config.transactions") ? "active" : "" }}"><span class="ico">&#128178;</span> Transactions</a>
        <a href="{{ route("admin.quotes.index") }}" class="sb-link {{ request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") ? "active" : "" }}"><span class="ico">&#128203;</span> Quotes</a>
        <a href="{{ route("admin.config.billable-items") }}" class="sb-link {{ request()->routeIs("admin.config.billable-items") ? "active" : "" }}"><span class="ico">&#128179;</span> Billable Items</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Support</div>
        <a href="{{ route("admin.tickets.index") }}" class="sb-link {{ request()->routeIs("admin.tickets.*") ? "active" : "" }}"><span class="ico">&#127915;</span> Tickets</a>
        <a href="{{ route("admin.config.announcements") }}" class="sb-link {{ request()->routeIs("admin.config.announcements") ? "active" : "" }}"><span class="ico">&#128227;</span> Announcements</a>
        <a href="{{ route("admin.config.knowledge-base") }}" class="sb-link {{ request()->routeIs("admin.config.knowledge-base") ? "active" : "" }}"><span class="ico">&#128218;</span> Knowledge Base</a>
        <a href="{{ route("admin.config.network-issues") }}" class="sb-link {{ request()->routeIs("admin.config.network-issues") ? "active" : "" }}"><span class="ico">&#128308;</span> Network Issues</a>
        <a href="{{ route("admin.config.downloads") }}" class="sb-link {{ request()->routeIs("admin.config.downloads") ? "active" : "" }}"><span class="ico">&#128229;</span> Downloads</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Reports</div>
        <a href="{{ route("admin.reports.index") }}" class="sb-link {{ request()->routeIs("admin.reports.*") ? "active" : "" }}"><span class="ico">&#128202;</span> Reports</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Utilities</div>
        <a href="{{ route("admin.config.todo") }}" class="sb-link {{ request()->routeIs("admin.config.todo") ? "active" : "" }}"><span class="ico">&#9745;</span> To-Do List</a>
        <a href="{{ route("admin.config.activity-log") }}" class="sb-link {{ request()->routeIs("admin.config.activity-log") ? "active" : "" }}"><span class="ico">&#128221;</span> Activity Log</a>
        <a href="{{ route("admin.logs.index") }}" class="sb-link {{ request()->routeIs("admin.logs.*") ? "active" : "" }}"><span class="ico">&#128466;</span> System Logs</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Configuration</div>
        <a href="{{ route("admin.settings.general") }}" class="sb-link {{ request()->routeIs("admin.settings.*") ? "active" : "" }}"><span class="ico">&#9881;</span> General Settings</a>
        <a href="{{ route("admin.config.admins") }}" class="sb-link {{ request()->routeIs("admin.config.admins") ? "active" : "" }}"><span class="ico">&#128101;</span> Staff</a>
        <a href="{{ route("admin.config.admin-roles") }}" class="sb-link {{ request()->routeIs("admin.config.admin-roles") ? "active" : "" }}"><span class="ico">&#128274;</span> Roles</a>
        <a href="{{ route("admin.config.currencies") }}" class="sb-link {{ request()->routeIs("admin.config.currencies") ? "active" : "" }}"><span class="ico">&#128176;</span> Currencies</a>
        <a href="{{ route("admin.config.tax") }}" class="sb-link {{ request()->routeIs("admin.config.tax") ? "active" : "" }}"><span class="ico">&#128200;</span> Tax Rates</a>
        <a href="{{ route("admin.config.gateways") }}" class="sb-link {{ request()->routeIs("admin.config.gateways") ? "active" : "" }}"><span class="ico">&#128179;</span> Gateways</a>
        <a href="{{ route("admin.config.promotions") }}" class="sb-link {{ request()->routeIs("admin.config.promotions") ? "active" : "" }}"><span class="ico">&#127991;</span> Promotions</a>
        <a href="{{ route("admin.config.servers") }}" class="sb-link {{ request()->routeIs("admin.config.servers") ? "active" : "" }}"><span class="ico">&#128421;</span> Servers</a>
        <a href="{{ route("admin.config.domain-pricing") }}" class="sb-link {{ request()->routeIs("admin.config.domain-pricing") ? "active" : "" }}"><span class="ico">&#127758;</span> Domain Pricing</a>
        <a href="{{ route("admin.config.registrars") }}" class="sb-link {{ request()->routeIs("admin.config.registrars") ? "active" : "" }}"><span class="ico">&#127760;</span> Registrars</a>
        <a href="{{ route("admin.config.ticket-departments") }}" class="sb-link {{ request()->routeIs("admin.config.ticket-departments") ? "active" : "" }}"><span class="ico">&#127970;</span> Departments</a>
        <a href="{{ route("admin.config.ticket-statuses") }}" class="sb-link {{ request()->routeIs("admin.config.ticket-statuses") ? "active" : "" }}"><span class="ico">&#127991;</span> Statuses</a>
        <a href="{{ route("admin.config.email-templates") }}" class="sb-link {{ request()->routeIs("admin.config.email-templates") ? "active" : "" }}"><span class="ico">&#9993;</span> Email Templates</a>
        <a href="{{ route("admin.config.api-credentials") }}" class="sb-link {{ request()->routeIs("admin.config.api-credentials") ? "active" : "" }}"><span class="ico">&#128273;</span> API Keys</a>
        <a href="{{ route("admin.config.banned-ips") }}" class="sb-link {{ request()->routeIs("admin.config.banned-ips") ? "active" : "" }}"><span class="ico">&#128683;</span> Banned IPs</a>
        <a href="{{ route("admin.config.system-database") }}" class="sb-link {{ request()->routeIs("admin.config.system-database") ? "active" : "" }}"><span class="ico">&#128295;</span> System Info</a>
    </div>
</aside>

{{-- ── CONTEXT SIDEBAR (navbar mode) ── --}}
<aside id="ctx-sidebar">
    @if(request()->routeIs("admin.dashboard"))
        <div class="cs-section"><div class="cs-hdr">Quick Links</div>
            <a href="{{ route("admin.clients.create") }}" class="cs-link"><span class="ico">&#43;</span> Add Client</a>
            <a href="{{ route("admin.invoices.create") }}" class="cs-link"><span class="ico">&#128196;</span> New Invoice</a>
            <a href="{{ route("admin.tickets.index") }}" class="cs-link"><span class="ico">&#127915;</span> Support Tickets</a>
            <a href="{{ route("admin.reports.index") }}" class="cs-link"><span class="ico">&#128202;</span> Reports</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">System</div>
            <a href="{{ route("admin.config.activity-log") }}" class="cs-link">Activity Log</a>
            <a href="{{ route("admin.config.system-database") }}" class="cs-link">System Info</a>
        </div>
    @elseif(request()->routeIs("admin.clients.*") || request()->routeIs("admin.services.*") || request()->routeIs("admin.domains.*"))
        <div class="cs-section"><div class="cs-hdr">Clients</div>
            <a href="{{ route("admin.clients.index") }}" class="cs-link {{ request()->routeIs("admin.clients.index") ? "active" : "" }}">All Clients</a>
            <a href="{{ route("admin.clients.create") }}" class="cs-link {{ request()->routeIs("admin.clients.create") ? "active" : "" }}">Add Client</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Products</div>
            <a href="{{ route("admin.services.index") }}" class="cs-link {{ request()->routeIs("admin.services.*") ? "active" : "" }}">Services</a>
            <a href="{{ route("admin.domains.index") }}" class="cs-link {{ request()->routeIs("admin.domains.*") ? "active" : "" }}">Domains</a>
        </div>
    @elseif(request()->routeIs("admin.invoices.*") || request()->routeIs("admin.config.transactions") || request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") || request()->routeIs("admin.config.billable-items"))
        <div class="cs-section"><div class="cs-hdr">Billing</div>
            <a href="{{ route("admin.invoices.index") }}" class="cs-link {{ request()->routeIs("admin.invoices.*") ? "active" : "" }}">Invoices</a>
            <a href="{{ route("admin.config.transactions") }}" class="cs-link {{ request()->routeIs("admin.config.transactions") ? "active" : "" }}">Transactions</a>
            <a href="{{ route("admin.quotes.index") }}" class="cs-link {{ request()->routeIs("admin.quotes.*") ? "active" : "" }}">Quotes</a>
            <a href="{{ route("admin.config.billable-items") }}" class="cs-link {{ request()->routeIs("admin.config.billable-items") ? "active" : "" }}">Billable Items</a>
        </div>
    @elseif(request()->routeIs("admin.tickets.*"))
        <div class="cs-section"><div class="cs-hdr">Tickets</div>
            <a href="{{ route("admin.tickets.index") }}" class="cs-link active">All Tickets</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Content</div>
            <a href="{{ route("admin.config.announcements") }}" class="cs-link">Announcements</a>
            <a href="{{ route("admin.config.knowledge-base") }}" class="cs-link">Knowledge Base</a>
            <a href="{{ route("admin.config.network-issues") }}" class="cs-link">Network Issues</a>
        </div>
    @elseif(request()->routeIs("admin.config.*") || request()->routeIs("admin.settings.*") || request()->routeIs("admin.logs.*"))
        <div class="cs-section"><div class="cs-hdr">Setup</div>
            <a href="{{ route("admin.settings.general") }}" class="cs-link {{ request()->routeIs("admin.settings.*") ? "active" : "" }}">General Settings</a>
            <a href="{{ route("admin.config.promotions") }}" class="cs-link {{ request()->routeIs("admin.config.promotions") ? "active" : "" }}">Promotions</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Staff</div>
            <a href="{{ route("admin.config.admins") }}" class="cs-link {{ request()->routeIs("admin.config.admins") ? "active" : "" }}">Admins</a>
            <a href="{{ route("admin.config.admin-roles") }}" class="cs-link {{ request()->routeIs("admin.config.admin-roles") ? "active" : "" }}">Roles</a>
            <a href="{{ route("admin.config.api-credentials") }}" class="cs-link {{ request()->routeIs("admin.config.api-credentials") ? "active" : "" }}">API Keys</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Payments</div>
            <a href="{{ route("admin.config.currencies") }}" class="cs-link {{ request()->routeIs("admin.config.currencies") ? "active" : "" }}">Currencies</a>
            <a href="{{ route("admin.config.tax") }}" class="cs-link {{ request()->routeIs("admin.config.tax") ? "active" : "" }}">Tax Rates</a>
            <a href="{{ route("admin.config.gateways") }}" class="cs-link {{ request()->routeIs("admin.config.gateways") ? "active" : "" }}">Gateways</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Products</div>
            <a href="{{ route("admin.config.servers") }}" class="cs-link {{ request()->routeIs("admin.config.servers") ? "active" : "" }}">Servers</a>
            <a href="{{ route("admin.config.domain-pricing") }}" class="cs-link {{ request()->routeIs("admin.config.domain-pricing") ? "active" : "" }}">Domain Pricing</a>
            <a href="{{ route("admin.config.registrars") }}" class="cs-link {{ request()->routeIs("admin.config.registrars") ? "active" : "" }}">Registrars</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Support</div>
            <a href="{{ route("admin.config.ticket-departments") }}" class="cs-link {{ request()->routeIs("admin.config.ticket-departments") ? "active" : "" }}">Departments</a>
            <a href="{{ route("admin.config.ticket-statuses") }}" class="cs-link {{ request()->routeIs("admin.config.ticket-statuses") ? "active" : "" }}">Statuses</a>
            <a href="{{ route("admin.config.email-templates") }}" class="cs-link {{ request()->routeIs("admin.config.email-templates") ? "active" : "" }}">Email Templates</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">System</div>
            <a href="{{ route("admin.config.activity-log") }}" class="cs-link {{ request()->routeIs("admin.config.activity-log") ? "active" : "" }}">Activity Log</a>
            <a href="{{ route("admin.config.todo") }}" class="cs-link {{ request()->routeIs("admin.config.todo") ? "active" : "" }}">To-Do</a>
            <a href="{{ route("admin.config.system-database") }}" class="cs-link {{ request()->routeIs("admin.config.system-database") ? "active" : "" }}">System Info</a>
            <a href="{{ route("admin.logs.index") }}" class="cs-link {{ request()->routeIs("admin.logs.*") ? "active" : "" }}">Logs</a>
        </div>
    @else
        <div class="cs-section"><div class="cs-hdr">Navigation</div>
            <a href="{{ route("admin.dashboard") }}" class="cs-link">Dashboard</a>
            <a href="{{ route("admin.clients.index") }}" class="cs-link">Clients</a>
            <a href="{{ route("admin.orders.index") }}" class="cs-link">Orders</a>
            <a href="{{ route("admin.invoices.index") }}" class="cs-link">Invoices</a>
            <a href="{{ route("admin.tickets.index") }}" class="cs-link">Tickets</a>
        </div>
    @endif
</aside>

{{-- ── CONTENT ── --}}
<main id="content-area">
    @if(session("success"))<div class="flash flash-ok">{{ session("success") }}</div>@endif
    @if(session("error"))<div class="flash flash-err">{{ session("error") }}</div>@endif
    @if(session("warning"))<div class="flash flash-warn">{{ session("warning") }}</div>@endif
    @if($errors->any())<div class="flash flash-err"><b>Please fix:</b><ul style="margin:4px 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield("content")
</main>

</div>

<script>
function toggleLayoutMode() {
    var body = document.body;
    if (body.classList.contains("mode-navbar")) {
        body.classList.remove("mode-navbar");
        body.classList.add("mode-sidebar");
        localStorage.setItem("pnlcs-layout", "sidebar");
        document.getElementById("mode-label").textContent = "Navbar";
    } else {
        body.classList.remove("mode-sidebar");
        body.classList.add("mode-navbar");
        localStorage.setItem("pnlcs-layout", "navbar");
        document.getElementById("mode-label").textContent = "Sidebar";
    }
}
(function() {
    var mode = localStorage.getItem("pnlcs-layout") || "navbar";
    document.body.classList.remove("mode-navbar", "mode-sidebar");
    document.body.classList.add("mode-" + mode);
    var label = document.getElementById("mode-label");
    if (label) label.textContent = mode === "navbar" ? "Sidebar" : "Navbar";
})();
function toggleMobile() {
    var sb = document.body.classList.contains("mode-sidebar") ? document.getElementById("full-sidebar") : document.getElementById("ctx-sidebar");
    var ov = document.getElementById("mob-overlay");
    sb.classList.toggle("open");
    ov.classList.toggle("show");
}
</script>
@stack("scripts")
</body>
</html>
