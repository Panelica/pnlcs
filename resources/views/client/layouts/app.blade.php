<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My Account') - PNLCS</title>
    @vite(['resources/css/app.css'])
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; background: #f6f6f6; color: #333; margin: 0; }
        /* Top navbar */
        .client-navbar { background: #fff; border-bottom: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 1000; }
        .client-navbar .navbar-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; height: 48px; }
        .client-navbar .navbar-brand { color: #1a4d80; font-size: 18px; font-weight: 700; text-decoration: none; margin-right: 24px; flex-shrink: 0; letter-spacing: -0.5px; }
        .client-navbar .navbar-nav { display: flex; align-items: center; gap: 2px; flex: 1; }
        .client-navbar .nav-link { display: flex; align-items: center; gap: 4px; padding: 6px 10px; font-size: 13px; color: #555; text-decoration: none; border-radius: 3px; white-space: nowrap; }
        .client-navbar .nav-link:hover, .client-navbar .nav-link.active { color: #1a4d80; background: #eff6ff; }
        .client-navbar .nav-dropdown { position: relative; }
        .client-navbar .nav-dropdown .nav-link { cursor: pointer; }
        .client-navbar .nav-dropdown .nav-link svg { width: 10px; height: 10px; margin-left: 2px; }
        .client-navbar .dropdown-menu { display: none; position: absolute; top: calc(100% + 2px); left: 0; min-width: 180px; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 999; padding: 4px 0; }
        .client-navbar .nav-dropdown:hover .dropdown-menu { display: block; }
        .client-navbar .dropdown-menu a { display: block; padding: 7px 14px; font-size: 13px; color: #444; text-decoration: none; }
        .client-navbar .dropdown-menu a:hover { background: #f5f5f5; color: #1a4d80; }
        .client-navbar .dropdown-menu .divider { height: 1px; background: #eee; margin: 3px 0; }
        .client-navbar .navbar-right { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .client-navbar .navbar-right .nav-link { font-size: 13px; }
        .client-navbar .navbar-right .btn { font-size: 12px; padding: 4px 12px; }
        /* Mobile */
        .navbar-toggle { display: none; background: none; border: 1px solid #ddd; border-radius: 3px; padding: 5px 8px; cursor: pointer; }
        .navbar-toggle span { display: block; width: 18px; height: 2px; background: #555; margin: 3px 0; }
        .mobile-menu { display: none; background: #fff; border-top: 1px solid #eee; padding: 8px 0; }
        .mobile-menu a { display: block; padding: 8px 20px; font-size: 13px; color: #444; text-decoration: none; }
        .mobile-menu a:hover { background: #f5f5f5; color: #1a4d80; }
        .mobile-menu .mobile-section { padding: 4px 20px 2px; font-size: 11px; font-weight: 600; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        @media (max-width: 900px) {
            .client-navbar .navbar-nav { display: none; }
            .navbar-toggle { display: block; }
            .mobile-menu.open { display: block; }
        }
        /* Main wrapper */
        .client-main { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }
        /* Footer */
        .client-footer { background: #fff; border-top: 1px solid #e0e0e0; padding: 16px 20px; text-align: center; font-size: 12px; color: #999; margin-top: 40px; }
        /* Alert boxes */
        .alert { padding: 10px 14px; border-radius: 4px; border: 1px solid transparent; font-size: 13px; margin-bottom: 14px; }
        .alert-success { background: #dff0d8; border-color: #d6e9c6; color: #3c763d; }
        .alert-error, .alert-danger { background: #f2dede; border-color: #ebccd1; color: #a94442; }
        .alert-info { background: #d9edf7; border-color: #bce8f1; color: #31708f; }
        .alert-warning { background: #fcf8e3; border-color: #faebcc; color: #8a6d3b; }
    </style>
    @yield('styles')
</head>
<body>

<nav class="client-navbar">
    <div class="navbar-inner">
        <a href="{{ route('client.home') }}" class="navbar-brand">PNLCS</a>

        <div class="navbar-nav">
            <a href="{{ route('client.home') }}" class="nav-link {{ request()->routeIs('client.home') ? 'active' : '' }}">Dashboard</a>

            <div class="nav-dropdown">
                <a class="nav-link {{ request()->routeIs('client.services.*') ? 'active' : '' }}">
                    Services <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('client.services.index') }}">My Services</a>
                    <a href="{{ route('client.store') }}">Order New Service</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <a class="nav-link {{ request()->routeIs('client.domains.*') ? 'active' : '' }}">
                    Domains <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('client.domains.index') }}">My Domains</a>
                    <a href="#">Register Domain</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <a class="nav-link {{ request()->routeIs('client.invoices.*') || request()->routeIs('client.funds.*') ? 'active' : '' }}">
                    Billing <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('client.invoices.index') }}">Invoices</a>
                    <a href="{{ route('client.funds.index') }}">Add Funds</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <a class="nav-link {{ request()->routeIs('client.tickets.*') || request()->routeIs('client.kb.*') || request()->routeIs('client.announcements.*') || request()->routeIs('client.contact') || request()->routeIs('client.downloads.*') ? 'active' : '' }}">
                    Support <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('client.tickets.create') }}">Open Ticket</a>
                    <a href="{{ route('client.tickets.index') }}">My Tickets</a>
                    <a href="{{ route('client.kb.index') }}">Knowledge Base</a>
                    <a href="{{ route('client.announcements.index') }}">Announcements</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <a class="nav-link {{ request()->routeIs('client.account.*') ? 'active' : '' }}">
                    Account <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('client.account.profile') }}">Profile</a>
                    <a href="{{ route('client.account.password') }}">Password</a>
                    <a href="{{ route('client.account.contacts') }}">Contacts</a>
                    <a href="{{ route('client.account.security') }}">Security</a>
                </div>
            </div>
        </div>

        <div class="navbar-right">
            @auth
                <span style="font-size:13px; color:#555;">{{ auth()->user()->first_name }}</span>
                <form method="POST" action="{{ route('client.logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-default btn-sm">Logout</button>
                </form>
            @else
                <a href="{{ route('client.login') }}" class="nav-link">Login</a>
                <a href="{{ route('client.register') }}" class="btn btn-primary btn-sm">Register</a>
            @endauth
        </div>

        <button class="navbar-toggle" onclick="document.getElementById('mobileMenu').classList.toggle('open')">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div id="mobileMenu" class="mobile-menu">
        <a href="{{ route('client.home') }}">Dashboard</a>
        <div class="mobile-section">Services</div>
        <a href="{{ route('client.services.index') }}">My Services</a>
        <a href="{{ route('client.store') }}">Order New Service</a>
        <div class="mobile-section">Domains</div>
        <a href="{{ route('client.domains.index') }}">My Domains</a>
        <div class="mobile-section">Billing</div>
        <a href="{{ route('client.invoices.index') }}">Invoices</a>
        <a href="{{ route('client.funds.index') }}">Add Funds</a>
        <div class="mobile-section">Support</div>
        <a href="{{ route('client.tickets.create') }}">Open Ticket</a>
        <a href="{{ route('client.tickets.index') }}">My Tickets</a>
        <a href="{{ route('client.kb.index') }}">Knowledge Base</a>
        <a href="{{ route('client.announcements.index') }}">Announcements</a>
        <div class="mobile-section">Account</div>
        <a href="{{ route('client.account.profile') }}">Profile</a>
        <a href="{{ route('client.account.password') }}">Password</a>
        <a href="{{ route('client.account.contacts') }}">Contacts</a>
        <a href="{{ route('client.account.security') }}">Security</a>
        @auth
            <form method="POST" action="{{ route('client.logout') }}" style="margin:0; padding: 0 20px 8px;">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px; width:100%;">Logout</button>
            </form>
        @endauth
    </div>
</nav>

<div class="client-main">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @yield('content')
</div>

<footer class="client-footer">
    &copy; {{ date('Y') }} PNLCS. All rights reserved.
    &nbsp;&middot;&nbsp; <a href="{{ route('client.contact') }}" style="color:#337ab7;">Contact</a>
    &nbsp;&middot;&nbsp; <a href="{{ route('client.announcements.index') }}" style="color:#337ab7;">Announcements</a>
    &nbsp;&middot;&nbsp; <a href="{{ route('client.kb.index') }}" style="color:#337ab7;">Knowledge Base</a>
</footer>

@yield('scripts')
</body>
</html>
