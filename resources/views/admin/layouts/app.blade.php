<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Admin") - {{ company_name() }}</title>
    @vite(["resources/css/app.css"])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    <style>
        .sidebar.collapsed { width: 0 !important; min-height: 0; overflow: hidden; padding: 0; border: 0; }
        .sidebar.collapsed + .contentarea { margin-left: 0 !important; border-left: none; }
        .sidebar-toggle-btn { position: fixed; bottom: 16px; left: 16px; z-index: 2000; width: 28px; height: 28px; background: var(--theme-primary, #1a4d80); color: #fff; border: none; border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 28px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.25); transition: left 0.2s; }
        .sidebar.collapsed ~ .sidebar-toggle-btn { left: 8px; }
        @media (max-width: 768px) { .sidebar-toggle-btn { display: none; } }
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 14px;font-size:13px;font-weight:600;border-radius:4px;border:none;cursor:pointer;text-decoration:none;transition:all 0.15s;line-height:1.3;white-space:nowrap;vertical-align:middle}        .btn svg,.btn i{flex-shrink:0;line-height:1}        .btn-primary{background:var(--theme-primary, #1a4d80);color:#fff}.btn-primary:hover{background:var(--theme-primary-dark, #143d66);color:#fff}        .btn-default{background:#f5f5f5;color:#333;border:1px solid #ddd}.btn-default:hover{background:#e8e8e8}        .btn-success{background:#5cb85c;color:#fff}.btn-success:hover{background:#449d44}        .btn-danger{background:#d9534f;color:#fff}.btn-danger:hover{background:#c9302c}        .btn-sm{padding:5px 10px;font-size:12px}        .btn-xs{padding:3px 8px;font-size:11px}
    </style>

    @if(($textDirection ?? 'ltr') === 'rtl')
    <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
</head>
<body>

{{-- ═══════════════════════════════════════════════
     NAVIGATION BAR (WHMCS Blend exact structure)
     ═══════════════════════════════════════════════ --}}
<div class="navigation clearfix" style="background-color:var(--theme-nav-bg, #1a4d80);">
    {{-- Logo --}}
    <a href="{{ route('admin.dashboard') }}" class="logo">
        @if(!empty($customLogo))
            <img src="{{ $customLogo }}" alt="Logo" style="max-height:30px; vertical-align:middle;">
        @else
            {{ company_name() }}
        @endif
    </a>

    {{-- Mobile toggle --}}
    <ul class="left-nav" style="float:left;">
        <li class="nav-toggle-li" style="float:left;">
            <a href="#" class="nav-toggle" onclick="event.preventDefault(); document.querySelector('.navbar-collapse').classList.toggle('open');">
                <i class="fas fa-bars"></i>
            </a>
        </li>
    </ul>

    {{-- Main navigation (horizontal at 1275px+) --}}
    <div class="navbar-collapse">
        <ul>
            {{-- + Add New --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-plus"></i>{{ __('common.actions.add_new') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.clients.create') }}"><i class="fas fa-user"></i> {{ __('admin.nav.new_client') }}</a></li>
                    <li><a href="{{ route('admin.orders.index') }}"><i class="fas fa-cube"></i> {{ __('admin.nav.new_order') }}</a></li>
                    <li><a href="{{ route('admin.invoices.create') }}"><i class="fas fa-file-invoice"></i> {{ __('admin.nav.new_invoice') }}</a></li>
                    <li><a href="{{ route('admin.quotes.create') }}"><i class="fas fa-file-signature"></i> {{ __('admin.nav.new_quote') }}</a></li>
                    <li><a href="{{ route('admin.tickets.index') }}"><i class="fas fa-life-ring"></i> {{ __('admin.nav.new_ticket') }}</a></li>
                </ul>
            </li>

            {{-- Clients --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-user"></i> {{ __('admin.nav.clients') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.clients.index') }}">{{ __('admin.nav.view_search_clients') }}</a></li>
                    <li><a href="{{ route('admin.clients.create') }}">{{ __('admin.nav.add_new_client') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.services.index') }}">{{ __('admin.nav.products_services') }}</a></li>
                    <li><a href="{{ route('admin.domains.index') }}">{{ __('admin.nav.domains') }}</a></li>
                    <li><a href="{{ route('admin.ssl.index') }}"><i class="fas fa-lock"></i> {{ __('admin.nav.ssl_certificates') }}</a></li>
                </ul>
            </li>

            {{-- Orders --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-shopping-cart"></i> {{ __('admin.nav.orders') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.orders.index') }}">{{ __('admin.nav.list_all_orders') }}</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}">{{ __('admin.nav.pending') }}</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'active']) }}">{{ __('admin.nav.active') }}</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'fraud']) }}">{{ __('admin.nav.fraud') }}</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}">{{ __('admin.status.cancelled') }}</a></li>
                </ul>
            </li>

            {{-- Billing --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-credit-card"></i> {{ __('admin.nav.billing') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.invoices.index') }}">{{ __('admin.nav.invoices') }}</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'paid']) }}">{{ __('admin.nav.paid_invoices') }}</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'unpaid']) }}">{{ __('admin.nav.unpaid_invoices') }}</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}">{{ __('admin.nav.overdue_invoices') }}</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'cancelled']) }}">{{ __('admin.nav.cancelled_invoices') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.payment-notifications.index') }}">{{ __('admin.nav.payment_notifications') }} @if(($sidebarCounts->pending_payment_notifications ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->pending_payment_notifications }}</span>@endif</a></li>
                    <li><a href="{{ route('admin.config.transactions') }}">{{ __('admin.nav.transactions') }}</a></li>
                    <li><a href="{{ route('admin.config.billable-items') }}">{{ __('admin.nav.billable_items') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.quotes.index') }}">{{ __('admin.nav.quotes') }}</a></li>
                </ul>
            </li>

            {{-- Support --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-life-ring"></i> {{ __('admin.nav.support') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.tickets.index') }}">{{ __('admin.nav.support_tickets') }}</a></li>
                    <li><a href="{{ route('admin.tickets.index') }}">{{ __('admin.nav.open_ticket') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.announcements') }}">{{ __('admin.nav.announcements') }}</a></li>
                    <li><a href="{{ route('admin.config.downloads') }}">{{ __('admin.nav.downloads') }}</a></li>
                    <li><a href="{{ route('admin.config.knowledge-base') }}">{{ __('admin.nav.knowledge_base') }}</a></li>
                    <li><a href="{{ route('admin.config.network-issues') }}">{{ __('admin.nav.network_issues') }}</a></li>
                </ul>
            </li>

            {{-- Reports --}}
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.reports.index') }}"><i class="fas fa-chart-bar"></i> {{ __('admin.nav.reports') }}</a>
            </li>

            {{-- Utilities --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-wrench"></i> {{ __('admin.nav.utilities') }}</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.config.automation') }}">{{ __('admin.nav.automation_status') }}</a></li>
                    <li><a href="{{ route('admin.config.todo') }}">{{ __('admin.nav.todo_list') }}</a></li>
                    <li><a href="{{ route('admin.calendar') }}">{{ __('admin.nav.calendar') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.activity-log') }}">{{ __('admin.nav.activity_log') }}</a></li>
                    <li><a href="{{ route('admin.logs.index') }}">{{ __('admin.nav.system_logs') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.system-database') }}">{{ __('admin.nav.system_database') }}</a></li>
                    <li><a href="{{ route('admin.config.system-phpinfo') }}">{{ __('admin.nav.php_info') }}</a></li>
                    <li><a href="{{ route('admin.whois.index') }}">{{ __('admin.nav.whois_lookup') }}</a></li>
                </ul>
            </li>
        </ul>
    </div>

    {{-- Right-side items --}}
    <div class="nav-right-items">
        {{-- IntelliSearch --}}
        <div class="intellisearch" id="intellisearch">
            <form action="{{ route('admin.clients.index') }}" method="GET">
                <i class="fas fa-search" style="color:#fff;"></i>
                <input type="text" name="search" class="form-control" placeholder="{{ __('common.placeholder.search') }}"
                       onfocus="document.getElementById('intellisearch').classList.add('active')"
                       onblur="setTimeout(function(){ document.getElementById('intellisearch').classList.remove('active'); }, 200)">
            </form>
        </div>

        <ul style="list-style:none; margin:0; padding:0; display:flex; align-items:center; height:45px;">
            {{-- Pending Orders Badge --}}
            @if(($sidebarCounts->pending_orders ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" title="{{ __('admin.nav.pending_orders') }}" style="position:relative;">
                    <i class="fas fa-shopping-cart"></i> <span class="nav-badge nav-badge-warning">{{ $sidebarCounts->pending_orders }}</span>
                </a>
            </li>
            @endif

            {{-- Overdue Invoices Badge --}}
            @if(($sidebarCounts->overdue_invoices ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" title="{{ __('admin.nav.overdue_invoices') }}" style="position:relative;">
                    <i class="fas fa-dollar-sign"></i> <span class="nav-badge">{{ $sidebarCounts->overdue_invoices }}</span>
                </a>
            </li>
            @endif

            {{-- Open Tickets Badge --}}
            @if(($sidebarCounts->open_tickets ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.tickets.index') }}" title="{{ __('admin.nav.open_tickets') }}" style="position:relative;">
                    <i class="fas fa-ticket-alt"></i> <span class="nav-badge nav-badge-info">{{ $sidebarCounts->open_tickets }}</span>
                </a>
            </li>
            @endif

            {{-- Setup / Config --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-cogs"></i> {{ __('admin.nav.setup') }}</a>
                <ul class="dropdown-menu" style="right:0; left:auto;">
                    <li><a href="{{ route('admin.config.admins') }}">{{ __('admin.nav.admin_accounts') }}</a></li>
                    <li><a href="{{ route('admin.config.admin-roles') }}">{{ __('admin.nav.admin_roles') }}</a></li>
                    <li><a href="{{ route('admin.config.api-credentials') }}">{{ __('admin.nav.api_credentials') }}</a></li>
                    <li><a href="https://panelica.github.io/pnlcs/" target="_blank" rel="noopener">{{ __('admin.nav.user_guide') }}</a></li>
                    <li><a href="{{ route('admin.api-docs') }}">{{ __('admin.nav.api_documentation') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.products.index') }}">{{ __('admin.nav.products_services') }}</a></li>
                    <li><a href="{{ route('admin.config.servers') }}">{{ __('admin.nav.servers') }}</a></li>
                    <li><a href="{{ route('admin.config.server-groups') }}">{{ __('admin.nav.server_groups') }}</a></li>
                    <li><a href="{{ route('admin.config.domain-pricing') }}">{{ __('admin.nav.domain_pricing') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.gateways') }}">{{ __('admin.nav.payment_gateways') }}</a></li>
                    <li><a href="{{ route('admin.config.registrars') }}">{{ __('admin.nav.domain_registrars') }}</a></li>
                    <li><a href="{{ route('admin.config.sslModules') }}">{{ __('admin.nav.ssl_modules') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.languages.index') }}"><i class="fas fa-language"></i> {{ __('admin.nav.languages') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.currencies') }}">{{ __('admin.nav.currencies') }}</a></li>
                    <li><a href="{{ route('admin.config.tax') }}">{{ __('admin.nav.tax_rules') }}</a></li>
                    <li><a href="{{ route('admin.config.custom-fields') }}">{{ __('admin.nav.custom_fields') }}</a></li>
                    <li><a href="{{ route('admin.config.promotions') }}">{{ __('admin.nav.promotions') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.ticket-departments') }}">{{ __('admin.nav.ticket_departments') }}</a></li>
                    <li><a href="{{ route('admin.config.ticket-statuses') }}">{{ __('admin.nav.ticket_statuses') }}</a></li>
                    <li><a href="{{ route('admin.config.email-templates') }}">{{ __('admin.nav.email_templates') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.settings.general') }}">{{ __('admin.nav.general_settings') }}</a></li>
                    <li><a href="{{ route('admin.settings.appearance') }}">{{ __('admin.nav.appearance') }}</a></li>
                    <li><a href="{{ route('admin.config.banned-ips') }}">{{ __('admin.nav.banned_ips') }}</a></li>
                    <li><a href="{{ route('admin.config.banned-emails') }}">{{ __('admin.nav.banned_emails') }}</a></li>
                    <li><a href="{{ route('admin.config.client-groups') }}">{{ __('admin.nav.client_groups') }}</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.notifications') }}">{{ __('admin.nav.notification_channels') }}</a></li>
                    <li><a href="{{ route('admin.config.ticket-spam') }}">{{ __('admin.nav.ticket_spam_filter') }}</a></li>
                    <li><a href="{{ route('admin.config.addons') }}">{{ __('admin.nav.product_addons') }}</a></li>
                    <li><a href="{{ route('admin.config.bundles') }}">{{ __('admin.nav.product_bundles') }}</a></li>
                </ul>
            </li>

            {{-- User Menu --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">
                    <i class="fas fa-user"></i> {{ auth('admin')->user()->full_name ?? 'Admin' }}
                </a>
                <ul class="dropdown-menu" style="right:0; left:auto;">
                    <li><a href="{{ route('admin.my-account') }}">{{ __('admin.nav.my_account') }}</a></li>
                    <li><a href="/" target="_blank">{{ __('admin.nav.client_area') }}</a></li>
                    <li class="divider"></li>
                    <li>
                        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">{{ __('common.actions.logout') }}</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:none;">
    @csrf
</form>

{{-- ═══════════════════════════════════════════════
     SIDEBAR (WHMCS Blend exact structure)
     ═══════════════════════════════════════════════ --}}
<div class="sidebar" id="sidebar">

    @php
        $routeName = Route::currentRouteName() ?? '';
        $segment = request()->segment(2) ?? 'dashboard';
    @endphp

    {{-- ── Dashboard Sidebar ── --}}
    @if($segment === '' || $segment === 'dashboard' || $routeName === 'admin.dashboard')
        <div class="sidebar-header"><i class="fas fa-star"></i> {{ __('admin.sidebar.shortcuts') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.clients.create') }}">{{ __('admin.nav.add_new_client') }}</a></li>
            <li><a href="{{ route('admin.invoices.create') }}">{{ __('admin.nav.create_invoice') }}</a></li>
            <li><a href="{{ route('admin.quotes.create') }}">{{ __('admin.sidebar.create_quote') }}</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}">{{ __('admin.nav.pending_orders') }} @if(($sidebarCounts->pending_orders ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->pending_orders }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}">{{ __('admin.nav.overdue_invoices') }} @if(($sidebarCounts->overdue_invoices ?? 0) > 0)<span class="sb-badge">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index') }}">{{ __('admin.nav.open_tickets') }} @if(($sidebarCounts->open_tickets ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->open_tickets }}</span>@endif</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-wrench"></i> {{ __('admin.sidebar.system_overview') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.automation') }}">{{ __('admin.nav.automation_status') }}</a></li>
            <li><a href="{{ route('admin.config.activity-log') }}">{{ __('admin.nav.activity_log') }}</a></li>
            <li><a href="{{ route('admin.logs.index') }}">{{ __('admin.nav.system_logs') }}</a></li>
            <li><a href="{{ route('admin.config.system-phpinfo') }}">{{ __('admin.nav.php_info') }}</a></li>
            <li><a href="{{ route('admin.whois.index') }}" @if($routeName === 'admin.whois.index' || $routeName === 'admin.whois.lookup') class="active" @endif>{{ __('admin.nav.whois_lookup') }}</a></li>
        </ul>

    {{-- ── Clients Sidebar ── --}}
    @elseif(str_starts_with($segment, 'client'))
        <div class="sidebar-header"><i class="fas fa-user"></i> {{ __('admin.sidebar.clients') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.clients.index') }}" @if($routeName === 'admin.clients.index') class="active" @endif>{{ __('admin.nav.view_search_clients') }}</a></li>
            <li><a href="{{ route('admin.clients.create') }}" @if($routeName === 'admin.clients.create') class="active" @endif>{{ __('admin.nav.add_new_client') }}</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-cube"></i> {{ __('admin.sidebar.services') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.services.index') }}" @if($routeName === 'admin.services.index') class="active" @endif>{{ __('admin.nav.products_services') }}</a></li>
            <li><a href="{{ route('admin.domains.index') }}" @if($routeName === 'admin.domains.index') class="active" @endif>{{ __('admin.nav.domains') }}</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-coins"></i> {{ __('admin.sidebar.affiliates') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.affiliates') }}">{{ __('admin.sidebar.affiliate_accounts') }}</a></li>
        </ul>

    {{-- ── Orders Sidebar ── --}}
    @elseif($segment === 'orders')
        <div class="sidebar-header"><i class="fas fa-cube"></i> {{ __('admin.sidebar.orders') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.orders.index') }}" @if(!request()->has('status')) class="active" @endif>{{ __('admin.sidebar.all_orders') }}</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" @if(request()->get('status') === 'pending') class="active" @endif>{{ __('admin.nav.pending') }} @if(($sidebarCounts->pending_orders ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->pending_orders }}</span>@endif</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'active']) }}" @if(request()->get('status') === 'active') class="active" @endif>{{ __('admin.nav.active') }}</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'fraud']) }}" @if(request()->get('status') === 'fraud') class="active" @endif>{{ __('admin.nav.fraud') }}</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}" @if(request()->get('status') === 'cancelled') class="active" @endif>{{ __('admin.status.cancelled') }}</a></li>
        </ul>

    {{-- ── Invoices / Billing Sidebar ── --}}
    @elseif($segment === 'invoices' || $segment === 'quotes' || $routeName === 'admin.config.transactions' || $routeName === 'admin.config.billable-items')
        <div class="sidebar-header"><i class="fas fa-money-bill-wave"></i> {{ __('admin.nav.billing') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.invoices.index') }}" @if($routeName === 'admin.invoices.index' && !request()->has('status')) class="active" @endif>{{ __('admin.sidebar.all_invoices') }}</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'paid']) }}" @if(request()->get('status') === 'paid') class="active" @endif>{{ __('admin.sidebar.paid') }}</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'unpaid']) }}" @if(request()->get('status') === 'unpaid') class="active" @endif>{{ __('admin.sidebar.unpaid') }} @if(($sidebarCounts->unpaid_invoices ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->unpaid_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" @if(request()->get('status') === 'overdue') class="active" @endif>{{ __('admin.sidebar.overdue') }} @if(($sidebarCounts->overdue_invoices ?? 0) > 0)<span class="sb-badge">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'cancelled']) }}" @if(request()->get('status') === 'cancelled') class="active" @endif>{{ __('admin.status.cancelled') }}</a></li>
            <li><a href="{{ route('admin.invoices.create') }}" @if($routeName === 'admin.invoices.create') class="active" @endif>{{ __('admin.sidebar.create_invoice') }}</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-coins"></i> {{ __('admin.nav.transactions') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.transactions') }}" @if($routeName === 'admin.config.transactions') class="active" @endif>{{ __('admin.nav.transactions') }}</a></li>
            <li><a href="{{ route('admin.config.billable-items') }}" @if($routeName === 'admin.config.billable-items') class="active" @endif>{{ __('admin.nav.billable_items') }}</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-file-signature"></i> {{ __('admin.nav.quotes') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.quotes.index') }}" @if($routeName === 'admin.quotes.index') class="active" @endif>{{ __('admin.nav.quotes') }}</a></li>
            <li><a href="{{ route('admin.quotes.create') }}" @if($routeName === 'admin.quotes.create') class="active" @endif>{{ __('admin.sidebar.create_quote') }}</a></li>
        </ul>

    {{-- ── Support / Tickets Sidebar ── --}}
    @elseif($segment === 'tickets')
        <div class="sidebar-header"><i class="fas fa-life-ring"></i> {{ __('admin.nav.support') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.tickets.index') }}" @if($routeName === 'admin.tickets.index' && !request()->has('status')) class="active" @endif>{{ __('admin.sidebar.all_tickets') }} @if(($sidebarCounts->active_tickets ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->active_tickets }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Open']) }}" @if(request()->get('status') === 'Open') class="active" @endif>{{ __('admin.sidebar.open') }} @if(($sidebarCounts->open_tickets_only ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->open_tickets_only }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Customer-Reply']) }}" @if(request()->get('status') === 'Customer-Reply') class="active" @endif>{{ __('admin.sidebar.awaiting_reply') }} @if(($sidebarCounts->awaiting_tickets ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->awaiting_tickets }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Closed']) }}" @if(request()->get('status') === 'Closed') class="active" @endif>{{ __('admin.status.closed') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-comments"></i> {{ __('admin.sidebar.filter_tickets_header') }}</div>
        <div class="advanced-search">
            <form action="{{ route('admin.tickets.index') }}" method="GET">
                <select name="department_id">
                    <option value="">{{ __('admin.sidebar.department') }}</option>
                    @foreach($sidebarDepartments ?? [] as $dep)
                    <option value="{{ $dep->id }}" @selected(request('department_id') == $dep->id)>{{ $dep->name }}</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="">{{ __('admin.sidebar.status') }}</option>
                    <option value="Open">{{ __('admin.sidebar.open') }}</option>
                    <option value="Answered">{{ __('admin.sidebar.answered') }}</option>
                    <option value="Customer-Reply">{{ __('admin.sidebar.customer_reply') }}</option>
                    <option value="Closed">{{ __('admin.sidebar.closed') }}</option>
                </select>
                <select name="priority">
                    <option value="">{{ __('admin.sidebar.priority') }}</option>
                    <option value="High">{{ __('admin.sidebar.high') }}</option>
                    <option value="Medium">{{ __('admin.sidebar.medium') }}</option>
                    <option value="Low">{{ __('admin.sidebar.low') }}</option>
                </select>
                <button type="submit" class="btn-go">{{ __('common.actions.filter') }}</button>
            </form>
        </div>

        <div class="sidebar-header"><i class="fas fa-bullhorn"></i> {{ __('admin.sidebar.content') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.announcements') }}">{{ __('admin.nav.announcements') }}</a></li>
            <li><a href="{{ route('admin.config.downloads') }}">{{ __('admin.nav.downloads') }}</a></li>
            <li><a href="{{ route('admin.config.knowledge-base') }}">{{ __('admin.nav.knowledge_base') }}</a></li>
            <li><a href="{{ route('admin.config.network-issues') }}">{{ __('admin.nav.network_issues') }}</a></li>
        </ul>

    {{-- ── Reports Sidebar ── --}}
    @elseif($segment === 'reports')
        <div class="sidebar-header"><i class="fas fa-chart-bar"></i> {{ __('admin.nav.reports') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.reports.index') }}" class="active">{{ __('admin.sidebar.reports_overview') }}</a></li>
        </ul>

    {{-- ── Config / Setup Sidebar ── --}}
    @elseif($segment === 'config' || $segment === 'settings' || $segment === 'products')
        <div class="sidebar-header"><i class="fas fa-users-cog"></i> {{ __('admin.sidebar.staff_management') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.admins') }}" @if($routeName === 'admin.config.admins') class="active" @endif>{{ __('admin.sidebar.administrator_accounts') }}</a></li>
            <li><a href="{{ route('admin.config.admin-roles') }}" @if($routeName === 'admin.config.admin-roles') class="active" @endif>{{ __('admin.sidebar.administrator_roles') }}</a></li>
            <li><a href="{{ route('admin.config.api-credentials') }}" @if($routeName === 'admin.config.api-credentials') class="active" @endif>{{ __('admin.nav.api_credentials') }}</a></li>
            <li><a href="https://panelica.github.io/pnlcs/" target="_blank" rel="noopener">{{ __('admin.nav.user_guide') }}</a></li>
            <li><a href="{{ route('admin.api-docs') }}" @if($routeName === 'admin.api-docs') class="active" @endif>{{ __('admin.nav.api_documentation') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-credit-card"></i> {{ __('admin.sidebar.payments') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.gateways') }}" @if($routeName === 'admin.config.gateways') class="active" @endif>{{ __('admin.nav.payment_gateways') }}</a></li>
            <li><a href="{{ route('admin.config.currencies') }}" @if($routeName === 'admin.config.currencies') class="active" @endif>{{ __('admin.nav.currencies') }}</a></li>
            <li><a href="{{ route('admin.config.tax') }}" @if($routeName === 'admin.config.tax') class="active" @endif>{{ __('admin.nav.tax_rules') }}</a></li>
            <li><a href="{{ route('admin.config.custom-fields') }}" @if($routeName === 'admin.config.custom-fields') class="active" @endif>{{ __('admin.nav.custom_fields') }}</a></li>
            <li><a href="{{ route('admin.config.promotions') }}" @if($routeName === 'admin.config.promotions') class="active" @endif>{{ __('admin.nav.promotions') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-cube"></i> {{ __('admin.sidebar.products') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.products.index') }}" @if($routeName === 'admin.products.index') class="active" @endif>{{ __('admin.nav.products_services') }}</a></li>
            <li><a href="{{ route('admin.products.create') }}" @if($routeName === 'admin.products.create') class="active" @endif>{{ __('admin.sidebar.create_product') }}</a></li>
            <li><a href="{{ route('admin.products.groups.create') }}" @if($routeName === 'admin.products.groups.create') class="active" @endif>{{ __('admin.sidebar.product_groups') }}</a></li>
            <li><a href="{{ route('admin.docker-apps.index') }}" @if($routeName === 'admin.docker-apps.index') class="active" @endif>{{ __('admin.nav.docker_apps') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-server"></i> {{ __('admin.sidebar.servers_domains') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.servers') }}" @if($routeName === 'admin.config.servers') class="active" @endif>{{ __('admin.nav.servers') }}</a></li>
            <li><a href="{{ route('admin.config.server-groups') }}" @if($routeName === 'admin.config.server-groups') class="active" @endif>{{ __('admin.nav.server_groups') }}</a></li>
            <li><a href="{{ route('admin.config.domain-pricing') }}" @if($routeName === 'admin.config.domain-pricing') class="active" @endif>{{ __('admin.nav.domain_pricing') }}</a></li>
            <li><a href="{{ route('admin.config.registrars') }}" @if($routeName === 'admin.config.registrars') class="active" @endif>{{ __('admin.nav.domain_registrars') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-life-ring"></i> {{ __('admin.nav.support') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.ticket-departments') }}" @if($routeName === 'admin.config.ticket-departments') class="active" @endif>{{ __('admin.nav.ticket_departments') }}</a></li>
            <li><a href="{{ route('admin.config.ticket-statuses') }}" @if($routeName === 'admin.config.ticket-statuses') class="active" @endif>{{ __('admin.nav.ticket_statuses') }}</a></li>
            <li><a href="{{ route('admin.config.email-templates') }}" @if($routeName === 'admin.config.email-templates') class="active" @endif>{{ __('admin.nav.email_templates') }}</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-wrench"></i> {{ __('admin.sidebar.other') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.settings.general') }}" @if($routeName === 'admin.settings.general') class="active" @endif>{{ __('admin.nav.general_settings') }}</a></li>
            <li><a href="{{ route('admin.settings.appearance') }}" @if($routeName === 'admin.settings.appearance') class="active" @endif><i class="fas fa-palette"></i> {{ __('admin.nav.appearance') }}</a></li>
            <li><a href="{{ route('admin.config.client-groups') }}" @if($routeName === 'admin.config.client-groups') class="active" @endif>{{ __('admin.nav.client_groups') }}</a></li>
            <li><a href="{{ route('admin.config.banned-ips') }}" @if($routeName === 'admin.config.banned-ips') class="active" @endif>{{ __('admin.nav.banned_ips') }}</a></li>
            <li><a href="{{ route('admin.config.banned-emails') }}" @if($routeName === 'admin.config.banned-emails') class="active" @endif>{{ __('admin.nav.banned_emails') }}</a></li>
            <li><a href="{{ route('admin.config.notifications') }}" @if($routeName === 'admin.config.notifications') class="active" @endif>{{ __('admin.nav.notification_channels') }}</a></li>
            <li><a href="{{ route('admin.config.ticket-spam') }}" @if($routeName === 'admin.config.ticket-spam') class="active" @endif>{{ __('admin.nav.ticket_spam_filter') }}</a></li>
            <li><a href="{{ route('admin.config.addons') }}" @if($routeName === 'admin.config.addons') class="active" @endif>{{ __('admin.nav.product_addons') }}</a></li>
                    <li><a href="/admin/config/addons/modules">{{ __('admin.nav.addon_modules') }}</a></li>
            <li><a href="{{ route('admin.config.bundles') }}" @if($routeName === 'admin.config.bundles') class="active" @endif>{{ __('admin.nav.product_bundles') }}</a></li>
        </ul>

    {{-- ── Logs Sidebar ── --}}
    @elseif($segment === 'logs')
        <div class="sidebar-header"><i class="fas fa-file-signature"></i> {{ __('admin.sidebar.logs') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.logs.index') }}" @if($routeName === 'admin.logs.index') class="active" @endif>{{ __('admin.nav.system_logs') }}</a></li>
            <li><a href="{{ route('admin.logs.gateway') }}" @if($routeName === 'admin.logs.gateway') class="active" @endif>{{ __('admin.sidebar.gateway_logs') }}</a></li>
            <li><a href="{{ route('admin.logs.module') }}" @if($routeName === 'admin.logs.module') class="active" @endif>{{ __('admin.sidebar.module_logs') }}</a></li>
            <li><a href="{{ route('admin.logs.email') }}" @if($routeName === 'admin.logs.email') class="active" @endif>{{ __('admin.sidebar.email_logs') }}</a></li>
        </ul>

    {{-- ── Calendar Sidebar ── --}}
    @elseif($segment === 'calendar')
        <div class="sidebar-header"><i class="fas fa-calendar-alt"></i> {{ __('admin.nav.calendar') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.calendar') }}" class="active">{{ __('admin.nav.calendar') }}</a></li>
            <li><a href="{{ route('admin.config.todo') }}">{{ __('admin.nav.todo_list') }}</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-wrench"></i> {{ __('admin.sidebar.utilities') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.automation') }}">{{ __('admin.nav.automation_status') }}</a></li>
            <li><a href="{{ route('admin.config.activity-log') }}">{{ __('admin.nav.activity_log') }}</a></li>
        </ul>

    {{-- ── Default Sidebar (fallback) ── --}}
    @else
        <div class="sidebar-header"><i class="fas fa-star"></i> {{ __('admin.sidebar.quick_links') }}</div>
        <ul class="menu">
            <li><a href="{{ route('admin.dashboard') }}">{{ __('admin.sidebar.dashboard') }}</a></li>
            <li><a href="{{ route('admin.clients.index') }}">{{ __('admin.sidebar.clients') }}</a></li>
            <li><a href="{{ route('admin.orders.index') }}">{{ __('admin.sidebar.orders') }}</a></li>
            <li><a href="{{ route('admin.invoices.index') }}">{{ __('admin.sidebar.invoices') }}</a></li>
            <li><a href="{{ route('admin.tickets.index') }}">{{ __('admin.sidebar.tickets') }}</a></li>
            <li><a href="{{ route('admin.reports.index') }}"><i class="fas fa-chart-bar"></i> {{ __('admin.nav.reports') }}</a></li>
        </ul>
    @endif

    {{-- ── Advanced Search (always visible) ── --}}
    <div class="sidebar-header"><i class="fas fa-binoculars"></i> {{ __('admin.sidebar.advanced_search') }}</div>
    <div class="advanced-search">
        <form action="{{ route('admin.clients.index') }}" method="GET">
            <input type="text" name="search" placeholder="{{ __('admin.sidebar.client_name_email') }}">
            <button type="submit" class="btn-go">{{ __('common.actions.search') }}</button>
        </form>
    </div>

    {{-- ── Staff Online ── --}}
    <div class="sidebar-header"><i class="fas fa-circle" style="color:#22c55e;font-size:8px;"></i> {{ __('admin.sidebar.staff_online') }}</div>
    <div class="staff-online">
        <div class="staff-row">
            <span class="staff-dot"></span>
            {{ auth('admin')->user()->full_name ?? 'Admin' }}
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════
     CONTENT AREA
     ═══════════════════════════════════════════════ --}}
<div class="contentarea" id="contentarea">
    

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    @yield('content')
</div>

{{-- ═══════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════ --}}
<div class="footerbar clearfix" style="background-color:var(--theme-footer-bg, #1a4d80);">
    <div style="float:left;">
        &copy; {{ date('Y') }} {{ company_name() }}@if(! branding_removed()) - {{ __('admin.footer.billing_support_system') }} &middot; <a href="https://github.com/Panelica/pnlcs" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">GitHub</a>@endif
    </div>
    <div style="float:right;">
        <a href="{{ route('admin.dashboard') }}">{{ __('admin.footer.admin_home') }}</a> |
        <a href="/" target="_blank">{{ __('admin.nav.client_area') }}</a> |
        <a href="{{ route('admin.logs.index') }}">{{ __('admin.nav.logs') }}</a>
    </div>
</div>

@vite(["resources/js/app.js"])
<button class="sidebar-toggle-btn" id="sidebarToggle" title="{{ __('admin.nav.toggle_sidebar') }}" onclick="toggleSidebar()">&#9776;</button>
<script>
(function(){
    var collapsed = localStorage.getItem("pnlcs_sidebar_collapsed") === "1";
    var sidebar = document.getElementById("sidebar");
    if (sidebar && collapsed) sidebar.classList.add("collapsed");
})();
function toggleSidebar() {
    var sidebar = document.getElementById("sidebar");
    if (!sidebar) return;
    sidebar.classList.toggle("collapsed");
    localStorage.setItem("pnlcs_sidebar_collapsed", sidebar.classList.contains("collapsed") ? "1" : "0");
}
</script>
@stack('scripts')
</body>
</html>
