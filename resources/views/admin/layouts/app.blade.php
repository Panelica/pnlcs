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
        body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; background: #e8edf2; color: #333; }

        /* ── TOP NAV ── */
        #top-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            height: 45px; background: #1a4d80;
            display: flex; align-items: center; padding: 0 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        #top-nav .nav-logo {
            color: #fff; font-size: 18px; font-weight: 700; text-decoration: none;
            letter-spacing: -0.5px; margin-right: 16px; white-space: nowrap;
        }
        #top-nav .nav-logo span { color: #7eb8f7; }
        #top-nav .hamburger {
            display: none; background: none; border: none; color: #fff;
            cursor: pointer; padding: 4px 8px; margin-right: 8px; font-size: 18px;
        }
        .top-nav-items {
            display: flex; align-items: stretch; height: 45px; flex: 1;
        }
        .top-nav-items > .nav-dropdown > a,
        .top-nav-items > .nav-item > a {
            display: flex; align-items: center; height: 45px;
            padding: 0 12px; color: #fff; font-size: 13px; font-weight: 500;
            text-decoration: none; white-space: nowrap; transition: background 0.1s;
            gap: 4px;
        }
        .top-nav-items > .nav-dropdown > a:hover,
        .top-nav-items > .nav-item > a:hover { background: #2f5b88; }
        .top-nav-items > .nav-dropdown > a.active,
        .top-nav-items > .nav-item > a.active { background: rgba(255,255,255,0.15); }
        .top-nav-items > .nav-dropdown > a .arrow { font-size: 10px; opacity: 0.7; }

        /* Dropdown menus */
        .nav-dropdown { position: relative; }
        .nav-dropdown-menu {
            display: none; position: absolute; top: 45px; left: 0;
            min-width: 200px; background: #fff; border: 1px solid #d0d0d0;
            border-top: 3px solid #1a4d80;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 2000;
        }
        .nav-dropdown:hover .nav-dropdown-menu,
        .nav-dropdown-menu:hover { display: block; }
        .nav-dropdown-menu a {
            display: block; padding: 8px 14px; color: #333; font-size: 13px;
            text-decoration: none; transition: background 0.1s;
        }
        .nav-dropdown-menu a:hover { background: #f0f4f8; color: #1a4d80; }
        .nav-dropdown-menu .divider { height: 1px; background: #e5e5e5; margin: 3px 0; }
        .nav-dropdown-menu .menu-header {
            padding: 6px 14px 4px; font-size: 11px; font-weight: 700;
            color: #888; text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Right nav section */
        .nav-right {
            display: flex; align-items: stretch; height: 45px; margin-left: auto;
        }
        .nav-right .nav-dropdown > a {
            display: flex; align-items: center; height: 45px;
            padding: 0 12px; color: #fff; font-size: 13px;
            text-decoration: none; gap: 6px; transition: background 0.1s;
        }
        .nav-right .nav-dropdown > a:hover { background: #2f5b88; }
        .nav-right .nav-dropdown .nav-dropdown-menu { right: 0; left: auto; }
        .nav-right .nav-icon { font-size: 15px; }
        .nav-user-avatar {
            width: 26px; height: 26px; background: rgba(255,255,255,0.2);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 12px; font-weight: 700; color: #fff;
        }

        /* ── LAYOUT WRAPPER ── */
        #layout-wrapper {
            display: flex; padding-top: 45px; min-height: 100vh;
        }

        /* ── CONTEXT SIDEBAR ── */
        #context-sidebar {
            width: 200px; min-height: calc(100vh - 45px);
            background: #f4f4f4; border-right: 1px solid #ddd;
            flex-shrink: 0; position: fixed; top: 45px; left: 0; bottom: 0;
            overflow-y: auto;
        }
        .sidebar-section { padding: 10px 0 4px; }
        .sidebar-section-header {
            padding: 6px 14px 5px; font-size: 11px; font-weight: 700;
            color: #888; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid #e0e0e0; margin-bottom: 2px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 7px;
            padding: 6px 14px; color: #555; font-size: 13px;
            text-decoration: none; transition: all 0.1s; border-left: 3px solid transparent;
        }
        .sidebar-link:hover { color: #1a4d80; background: #eaeef2; border-left-color: #1a4d80; }
        .sidebar-link.active { color: #1a4d80; background: #dce8f5; border-left-color: #1a4d80; font-weight: 600; }
        .sidebar-link .icon { width: 14px; text-align: center; opacity: 0.7; font-size: 12px; }
        .sidebar-divider { height: 1px; background: #e0e0e0; margin: 4px 14px; }

        /* ── CONTENT AREA ── */
        #content-area {
            margin-left: 200px; flex: 1; padding: 20px;
            min-width: 0;
        }

        /* Flash messages */
        .flash-message {
            padding: 10px 16px; border-radius: 4px; margin-bottom: 16px;
            font-size: 13px; border: 1px solid transparent;
        }
        .flash-success { background: #dff0d8; color: #3c763d; border-color: #d6e9c6; }
        .flash-error   { background: #f2dede; color: #a94442; border-color: #ebccd1; }
        .flash-warning { background: #fcf8e3; color: #8a6d3b; border-color: #faebcc; }
        .flash-info    { background: #d9edf7; color: #31708f; border-color: #bce8f1; }

        /* ── MOBILE RESPONSIVE ── */
        @media (max-width: 1023px) {
            #top-nav .hamburger { display: block; }
            .top-nav-items { display: none; }
            .nav-right { margin-left: auto; }
            #context-sidebar {
                transform: translateX(-200px); transition: transform 0.2s ease;
                z-index: 900;
            }
            #context-sidebar.open { transform: translateX(0); }
            #content-area { margin-left: 0; padding: 12px; }
            #mobile-overlay {
                display: none; position: fixed; inset: 0;
                background: rgba(0,0,0,0.4); z-index: 850;
            }
            #mobile-overlay.show { display: block; }
        }
        @media (min-width: 1024px) {
            #mobile-overlay { display: none !important; }
        }
    </style>
</head>
<body>

{{-- ── TOP NAVIGATION BAR ── --}}
<nav id="top-nav">
    {{-- Hamburger (mobile) --}}
    <button class="hamburger" onclick="toggleSidebar()" title="Toggle Menu">&#9776;</button>

    {{-- Logo --}}
    <a href="{{ route("admin.dashboard") }}" class="nav-logo">PNL<span>CS</span></a>

    {{-- Center nav items --}}
    <div class="top-nav-items">

        {{-- + Add New --}}
        <div class="nav-dropdown">
            <a href="#"><span style="font-size:16px;margin-right:2px;">+</span> Add New <span class="arrow">&#9660;</span></a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.clients.create") }}">&#128100; New Client</a>
                <a href="{{ route("admin.orders.index") }}">&#128722; New Order</a>
                <a href="{{ route("admin.invoices.create") }}">&#128196; New Invoice</a>
                <a href="{{ route("admin.quotes.create") }}">&#128203; New Quote</a>
                <a href="{{ route("admin.tickets.index") }}">&#127915; New Ticket</a>
            </div>
        </div>

        {{-- Clients --}}
        <div class="nav-dropdown">
            <a href="{{ route("admin.clients.index") }}" class="{{ request()->routeIs("admin.clients.*") ? "active" : "" }}">
                Clients <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.clients.index") }}">View/Search Clients</a>
                <a href="{{ route("admin.clients.create") }}">Add New Client</a>
                <div class="divider"></div>
                <a href="{{ route("admin.services.index") }}">Products/Services</a>
                <a href="{{ route("admin.domains.index") }}">Domains</a>
                <a href="#">Cancellation Requests</a>
            </div>
        </div>

        {{-- Orders --}}
        <div class="nav-dropdown">
            <a href="{{ route("admin.orders.index") }}" class="{{ request()->routeIs("admin.orders.*") ? "active" : "" }}">
                Orders <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.orders.index") }}">List All Orders</a>
                <a href="{{ route("admin.orders.index") }}?status=pending">Pending</a>
                <a href="{{ route("admin.orders.index") }}?status=active">Active</a>
                <a href="{{ route("admin.orders.index") }}?status=fraud">Fraud</a>
                <a href="{{ route("admin.orders.index") }}?status=cancelled">Cancelled</a>
            </div>
        </div>

        {{-- Billing --}}
        <div class="nav-dropdown">
            <a href="{{ route("admin.invoices.index") }}" class="{{ request()->routeIs("admin.invoices.*") || request()->routeIs("admin.config.transactions") || request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") || request()->routeIs("admin.config.billable-items") ? "active" : "" }}">
                Billing <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.invoices.index") }}">Invoices</a>
                <a href="{{ route("admin.config.transactions") }}">Transactions</a>
                <a href="{{ route("admin.quotes.index") }}">Quotes</a>
                <a href="{{ route("admin.config.billable-items") }}">Billable Items</a>
            </div>
        </div>

        {{-- Support --}}
        <div class="nav-dropdown">
            <a href="{{ route("admin.tickets.index") }}" class="{{ request()->routeIs("admin.tickets.*") || request()->routeIs("admin.config.announcements") || request()->routeIs("admin.config.downloads") || request()->routeIs("admin.config.knowledge-base") || request()->routeIs("admin.config.network-issues") ? "active" : "" }}">
                Support <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.tickets.index") }}">Tickets</a>
                <div class="divider"></div>
                <a href="{{ route("admin.config.announcements") }}">Announcements</a>
                <a href="{{ route("admin.config.downloads") }}">Downloads</a>
                <a href="{{ route("admin.config.knowledge-base") }}">Knowledge Base</a>
                <a href="{{ route("admin.config.network-issues") }}">Network Issues</a>
            </div>
        </div>

        {{-- Reports --}}
        <div class="nav-item">
            <a href="{{ route("admin.reports.index") }}" class="{{ request()->routeIs("admin.reports.*") ? "active" : "" }}">Reports</a>
        </div>

        {{-- Utilities --}}
        <div class="nav-dropdown">
            <a href="#" class="{{ request()->routeIs("admin.config.todo") || request()->routeIs("admin.config.activity-log") ? "active" : "" }}">
                Utilities <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu">
                <a href="{{ route("admin.config.todo") }}">To-Do List</a>
                <a href="{{ route("admin.config.activity-log") }}">Activity Log</a>
                <a href="{{ route("admin.config.downloads") }}">Downloads</a>
            </div>
        </div>

    </div>{{-- /.top-nav-items --}}

    {{-- Right side --}}
    <div class="nav-right">
        {{-- Sidebar Toggle --}}
        <button class="sidebar-toggle-btn" onclick="toggleSidebarPref()" title="Toggle Sidebar">
            &#9776;
        </button>

        {{-- Setup / Wrench --}}
        <div class="nav-dropdown">
            <a href="#" title="Setup">
                <span class="nav-icon">&#9881;</span> Setup <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu" style="min-width:220px;">
                <div class="menu-header">Staff &amp; Security</div>
                <a href="{{ route("admin.config.admins") }}">Admin Staff</a>
                <a href="{{ route("admin.config.admin-roles") }}">Admin Roles</a>
                <a href="{{ route("admin.config.api-credentials") }}">API Credentials</a>
                <a href="{{ route("admin.config.banned-ips") }}">Banned IPs</a>
                <a href="{{ route("admin.config.banned-emails") }}">Banned Emails</a>
                <div class="divider"></div>
                <div class="menu-header">Payments</div>
                <a href="{{ route("admin.config.currencies") }}">Currencies</a>
                <a href="{{ route("admin.config.tax") }}">Tax Rates</a>
                <a href="{{ route("admin.config.gateways") }}">Payment Gateways</a>
                <div class="divider"></div>
                <div class="menu-header">Products</div>
                <a href="{{ route("admin.config.servers") }}">Servers</a>
                <a href="{{ route("admin.config.domain-pricing") }}">Domain Pricing</a>
                <a href="{{ route("admin.config.registrars") }}">Domain Registrars</a>
                <div class="divider"></div>
                <div class="menu-header">Support</div>
                <a href="{{ route("admin.config.ticket-departments") }}">Ticket Departments</a>
                <a href="{{ route("admin.config.ticket-statuses") }}">Ticket Statuses</a>
                <a href="{{ route("admin.config.email-templates") }}">Email Templates</a>
                <div class="divider"></div>
                <div class="menu-header">System</div>
                <a href="{{ route("admin.settings.general") }}">General Settings</a>
                <a href="{{ route("admin.config.system-database") }}">System Information</a>
                <a href="{{ route("admin.config.system-phpinfo") }}">PHP Info</a>
            </div>
        </div>

        {{-- User menu --}}
        <div class="nav-dropdown">
            <a href="#">
                <div class="nav-user-avatar">{{ strtoupper(substr(auth("admin")->user()->full_name ?? "A", 0, 1)) }}</div>
                <span style="max-width:120px;overflow:hidden;text-overflow:ellipsis;">{{ auth("admin")->user()->full_name ?? "Admin" }}</span>
                <span class="arrow">&#9660;</span>
            </a>
            <div class="nav-dropdown-menu" style="right:0;left:auto;">
                <a href="{{ route("admin.config.admins") }}">&#128100; My Account</a>
                <a href="/" target="_blank">&#127968; Client Area</a>
                <div class="divider"></div>
                <a href="{{ route("admin.logout") }}"
                   onclick="event.preventDefault(); document.getElementById(\"admin-logout-form\").submit();">
                   &#128275; Logout
                </a>
                <form id="admin-logout-form" action="{{ route("admin.logout") }}" method="POST" style="display:none;">
                    @csrf
                </form>
            </div>
        </div>

    </div>{{-- /.nav-right --}}
</nav>

{{-- Mobile overlay --}}
<div id="mobile-overlay" onclick="toggleSidebar()"></div>

{{-- ── LAYOUT WRAPPER ── --}}
<div id="layout-wrapper">

    {{-- ── CONTEXT SIDEBAR ── --}}
    <aside id="context-sidebar">

        @if(request()->routeIs("admin.dashboard"))
            {{-- Dashboard sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Quick Links</div>
                <a href="{{ route("admin.clients.create") }}" class="sidebar-link">
                    <span class="icon">&#43;</span> Add Client
                </a>
                <a href="{{ route("admin.orders.index") }}" class="sidebar-link">
                    <span class="icon">&#128722;</span> New Order
                </a>
                <a href="{{ route("admin.invoices.index") }}" class="sidebar-link">
                    <span class="icon">&#128196;</span> Generate Invoices
                </a>
                <a href="{{ route("admin.tickets.index") }}" class="sidebar-link">
                    <span class="icon">&#127915;</span> Support Tickets
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">System</div>
                <a href="{{ route("admin.config.activity-log") }}" class="sidebar-link">
                    <span class="icon">&#128221;</span> Activity Log
                </a>
                <a href="{{ route("admin.config.system-database") }}" class="sidebar-link">
                    <span class="icon">&#128295;</span> System Info
                </a>
                <a href="{{ route("admin.reports.index") }}" class="sidebar-link">
                    <span class="icon">&#128202;</span> Reports
                </a>
            </div>

        @elseif(request()->routeIs("admin.clients.*") || request()->routeIs("admin.services.*") || request()->routeIs("admin.domains.*"))
            {{-- Clients sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Clients</div>
                <a href="{{ route("admin.clients.index") }}" class="sidebar-link {{ request()->routeIs("admin.clients.index") ? "active" : "" }}">
                    <span class="icon">&#128100;</span> All Clients
                </a>
                <a href="{{ route("admin.clients.create") }}" class="sidebar-link {{ request()->routeIs("admin.clients.create") ? "active" : "" }}">
                    <span class="icon">&#43;</span> Add Client
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Products</div>
                <a href="{{ route("admin.services.index") }}" class="sidebar-link {{ request()->routeIs("admin.services.*") ? "active" : "" }}">
                    <span class="icon">&#128230;</span> Products/Services
                </a>
                <a href="{{ route("admin.domains.index") }}" class="sidebar-link {{ request()->routeIs("admin.domains.*") ? "active" : "" }}">
                    <span class="icon">&#127758;</span> Domains
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Filter Clients</div>
                <a href="{{ route("admin.clients.index") }}?status=active" class="sidebar-link">Active</a>
                <a href="{{ route("admin.clients.index") }}?status=inactive" class="sidebar-link">Inactive</a>
                <a href="{{ route("admin.clients.index") }}?status=closed" class="sidebar-link">Closed</a>
            </div>

        @elseif(request()->routeIs("admin.invoices.*") || request()->routeIs("admin.config.transactions") || request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") || request()->routeIs("admin.config.billable-items"))
            {{-- Billing sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Billing</div>
                <a href="{{ route("admin.invoices.index") }}" class="sidebar-link {{ request()->routeIs("admin.invoices.*") ? "active" : "" }}">
                    <span class="icon">&#128196;</span> Invoices
                </a>
                <a href="{{ route("admin.config.transactions") }}" class="sidebar-link {{ request()->routeIs("admin.config.transactions") ? "active" : "" }}">
                    <span class="icon">&#128178;</span> Transactions
                </a>
                <a href="{{ route("admin.quotes.index") }}" class="sidebar-link {{ request()->routeIs("admin.quotes.*") ? "active" : "" }}">
                    <span class="icon">&#128203;</span> Quotes
                </a>
                <a href="{{ route("admin.config.billable-items") }}" class="sidebar-link {{ request()->routeIs("admin.config.billable-items") ? "active" : "" }}">
                    <span class="icon">&#128179;</span> Billable Items
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Invoices</div>
                <a href="{{ route("admin.invoices.index") }}?status=unpaid" class="sidebar-link">Unpaid</a>
                <a href="{{ route("admin.invoices.index") }}?status=overdue" class="sidebar-link">Overdue</a>
                <a href="{{ route("admin.invoices.index") }}?status=paid" class="sidebar-link">Paid</a>
                <a href="{{ route("admin.invoices.create") }}" class="sidebar-link">
                    <span class="icon">&#43;</span> Create Invoice
                </a>
            </div>

        @elseif(request()->routeIs("admin.tickets.*"))
            {{-- Support sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Tickets</div>
                <a href="{{ route("admin.tickets.index") }}" class="sidebar-link {{ request()->routeIs("admin.tickets.index") ? "active" : "" }}">
                    <span class="icon">&#127915;</span> All Tickets
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">By Status</div>
                <a href="{{ route("admin.tickets.index") }}?status=open" class="sidebar-link">Open</a>
                <a href="{{ route("admin.tickets.index") }}?status=answered" class="sidebar-link">Answered</a>
                <a href="{{ route("admin.tickets.index") }}?status=customer-reply" class="sidebar-link">Customer Reply</a>
                <a href="{{ route("admin.tickets.index") }}?status=closed" class="sidebar-link">Closed</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Support Content</div>
                <a href="{{ route("admin.config.announcements") }}" class="sidebar-link">Announcements</a>
                <a href="{{ route("admin.config.knowledge-base") }}" class="sidebar-link">Knowledge Base</a>
                <a href="{{ route("admin.config.network-issues") }}" class="sidebar-link">Network Issues</a>
                <a href="{{ route("admin.config.downloads") }}" class="sidebar-link">Downloads</a>
            </div>

        @elseif(request()->routeIs("admin.config.*") || request()->routeIs("admin.settings.*") || request()->routeIs("admin.logs.*"))
            {{-- Config sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Setup</div>
                <a href="{{ route("admin.settings.general") }}" class="sidebar-link {{ request()->routeIs("admin.settings.general") ? "active" : "" }}">
                    <span class="icon">&#9881;</span> General Settings
                </a>
                <a href="{{ route("admin.config.promotions") }}" class="sidebar-link {{ request()->routeIs("admin.config.promotions") ? "active" : "" }}">
                    <span class="icon">&#127991;</span> Promotions
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Staff &amp; Security</div>
                <a href="{{ route("admin.config.admins") }}" class="sidebar-link {{ request()->routeIs("admin.config.admins") ? "active" : "" }}">Admins</a>
                <a href="{{ route("admin.config.admin-roles") }}" class="sidebar-link {{ request()->routeIs("admin.config.admin-roles") ? "active" : "" }}">Admin Roles</a>
                <a href="{{ route("admin.config.api-credentials") }}" class="sidebar-link {{ request()->routeIs("admin.config.api-credentials") ? "active" : "" }}">API Credentials</a>
                <a href="{{ route("admin.config.banned-ips") }}" class="sidebar-link {{ request()->routeIs("admin.config.banned-ips") ? "active" : "" }}">Banned IPs</a>
                <a href="{{ route("admin.config.banned-emails") }}" class="sidebar-link {{ request()->routeIs("admin.config.banned-emails") ? "active" : "" }}">Banned Emails</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Payments</div>
                <a href="{{ route("admin.config.currencies") }}" class="sidebar-link {{ request()->routeIs("admin.config.currencies") ? "active" : "" }}">Currencies</a>
                <a href="{{ route("admin.config.tax") }}" class="sidebar-link {{ request()->routeIs("admin.config.tax") ? "active" : "" }}">Tax Rates</a>
                <a href="{{ route("admin.config.gateways") }}" class="sidebar-link {{ request()->routeIs("admin.config.gateways") ? "active" : "" }}">Gateways</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Products</div>
                <a href="{{ route("admin.config.servers") }}" class="sidebar-link {{ request()->routeIs("admin.config.servers") ? "active" : "" }}">Servers</a>
                <a href="{{ route("admin.config.domain-pricing") }}" class="sidebar-link {{ request()->routeIs("admin.config.domain-pricing") ? "active" : "" }}">Domain Pricing</a>
                <a href="{{ route("admin.config.registrars") }}" class="sidebar-link {{ request()->routeIs("admin.config.registrars") ? "active" : "" }}">Registrars</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Support</div>
                <a href="{{ route("admin.config.ticket-departments") }}" class="sidebar-link {{ request()->routeIs("admin.config.ticket-departments") ? "active" : "" }}">Ticket Departments</a>
                <a href="{{ route("admin.config.ticket-statuses") }}" class="sidebar-link {{ request()->routeIs("admin.config.ticket-statuses") ? "active" : "" }}">Ticket Statuses</a>
                <a href="{{ route("admin.config.email-templates") }}" class="sidebar-link {{ request()->routeIs("admin.config.email-templates") ? "active" : "" }}">Email Templates</a>
                <a href="{{ route("admin.config.announcements") }}" class="sidebar-link {{ request()->routeIs("admin.config.announcements") ? "active" : "" }}">Announcements</a>
                <a href="{{ route("admin.config.knowledge-base") }}" class="sidebar-link {{ request()->routeIs("admin.config.knowledge-base") ? "active" : "" }}">Knowledge Base</a>
                <a href="{{ route("admin.config.downloads") }}" class="sidebar-link {{ request()->routeIs("admin.config.downloads") ? "active" : "" }}">Downloads</a>
                <a href="{{ route("admin.config.network-issues") }}" class="sidebar-link {{ request()->routeIs("admin.config.network-issues") ? "active" : "" }}">Network Issues</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">System</div>
                <a href="{{ route("admin.config.activity-log") }}" class="sidebar-link {{ request()->routeIs("admin.config.activity-log") ? "active" : "" }}">Activity Log</a>
                <a href="{{ route("admin.config.todo") }}" class="sidebar-link {{ request()->routeIs("admin.config.todo") ? "active" : "" }}">To-Do List</a>
                <a href="{{ route("admin.config.system-database") }}" class="sidebar-link {{ request()->routeIs("admin.config.system-database") ? "active" : "" }}">System Info</a>
                <a href="{{ route("admin.config.system-phpinfo") }}" class="sidebar-link {{ request()->routeIs("admin.config.system-phpinfo") ? "active" : "" }}">PHP Info</a>
                <a href="{{ route("admin.logs.index") }}" class="sidebar-link {{ request()->routeIs("admin.logs.*") ? "active" : "" }}">System Logs</a>
            </div>

        @elseif(request()->routeIs("admin.reports.*"))
            {{-- Reports sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Reports</div>
                <a href="{{ route("admin.reports.index") }}" class="sidebar-link active">All Reports</a>
            </div>

        @elseif(request()->routeIs("admin.orders.*"))
            {{-- Orders sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Orders</div>
                <a href="{{ route("admin.orders.index") }}" class="sidebar-link {{ request()->routeIs("admin.orders.index") ? "active" : "" }}">
                    <span class="icon">&#128722;</span> All Orders
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-header">By Status</div>
                <a href="{{ route("admin.orders.index") }}?status=pending" class="sidebar-link">Pending</a>
                <a href="{{ route("admin.orders.index") }}?status=active" class="sidebar-link">Active</a>
                <a href="{{ route("admin.orders.index") }}?status=fraud" class="sidebar-link">Fraud</a>
                <a href="{{ route("admin.orders.index") }}?status=cancelled" class="sidebar-link">Cancelled</a>
            </div>

        @else
            {{-- Default / fallback sidebar --}}
            <div class="sidebar-section">
                <div class="sidebar-section-header">Navigation</div>
                <a href="{{ route("admin.dashboard") }}" class="sidebar-link {{ request()->routeIs("admin.dashboard") ? "active" : "" }}">
                    <span class="icon">&#127968;</span> Dashboard
                </a>
                <a href="{{ route("admin.clients.index") }}" class="sidebar-link {{ request()->routeIs("admin.clients.*") ? "active" : "" }}">
                    <span class="icon">&#128100;</span> Clients
                </a>
                <a href="{{ route("admin.orders.index") }}" class="sidebar-link {{ request()->routeIs("admin.orders.*") ? "active" : "" }}">
                    <span class="icon">&#128722;</span> Orders
                </a>
                <a href="{{ route("admin.invoices.index") }}" class="sidebar-link {{ request()->routeIs("admin.invoices.*") ? "active" : "" }}">
                    <span class="icon">&#128196;</span> Invoices
                </a>
                <a href="{{ route("admin.tickets.index") }}" class="sidebar-link {{ request()->routeIs("admin.tickets.*") ? "active" : "" }}">
                    <span class="icon">&#127915;</span> Tickets
                </a>
                <a href="{{ route("admin.reports.index") }}" class="sidebar-link {{ request()->routeIs("admin.reports.*") ? "active" : "" }}">
                    <span class="icon">&#128202;</span> Reports
                </a>
            </div>
        @endif

    </aside>{{-- /#context-sidebar --}}

    {{-- ── CONTENT AREA ── --}}
    <main id="content-area">

        {{-- Flash messages --}}
        @if(session("success"))
            <div class="flash-message flash-success">{{ session("success") }}</div>
        @endif
        @if(session("error"))
            <div class="flash-message flash-error">{{ session("error") }}</div>
        @endif
        @if(session("warning"))
            <div class="flash-message flash-warning">{{ session("warning") }}</div>
        @endif
        @if(session("info"))
            <div class="flash-message flash-info">{{ session("info") }}</div>
        @endif
        @if($errors->any())
            <div class="flash-message flash-error">
                <strong>Please fix the following errors:</strong>
                <ul style="margin:6px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Page content --}}
        @yield("content")

    </main>

</div>{{-- /#layout-wrapper --}}

{{-- Mobile sidebar JS --}}
<script>
function toggleSidebarPref() {
    document.body.classList.toggle("no-sidebar");
    localStorage.setItem("pnlcs-sidebar", document.body.classList.contains("no-sidebar") ? "hidden" : "visible");
}
// Restore sidebar preference on load
if (localStorage.getItem("pnlcs-sidebar") === "hidden") {
    document.body.classList.add("no-sidebar");
}
function toggleSidebar() {
    var sidebar = document.getElementById("context-sidebar");
    var overlay = document.getElementById("mobile-overlay");
    var isOpen = sidebar.classList.toggle("open");
    if (isOpen) {
        overlay.classList.add("show");
    } else {
        overlay.classList.remove("show");
    }
}
</script>

@stack("scripts")
</body>
</html>
