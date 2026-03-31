<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Admin") - PNLCS</title>
    @vite(["resources/css/app.css"])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
</head>
<body>

{{-- ═══════════════════════════════════════════════
     NAVIGATION BAR (WHMCS Blend exact structure)
     ═══════════════════════════════════════════════ --}}
<div class="navigation clearfix">
    {{-- Logo --}}
    <a href="{{ route('admin.dashboard') }}" class="logo">PNLCS</a>

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
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-plus"></i> Add New</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.clients.create') }}"><i class="fas fa-user"></i> New Client</a></li>
                    <li><a href="{{ route('admin.orders.index') }}"><i class="fas fa-cube"></i> New Order</a></li>
                    <li><a href="{{ route('admin.invoices.create') }}"><i class="fas fa-file-invoice"></i> New Invoice</a></li>
                    <li><a href="{{ route('admin.quotes.create') }}"><i class="fas fa-file-signature"></i> New Quote</a></li>
                    <li><a href="{{ route('admin.tickets.index') }}"><i class="fas fa-life-ring"></i> New Ticket</a></li>
                </ul>
            </li>

            {{-- Clients --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">Clients</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.clients.index') }}">View/Search Clients</a></li>
                    <li><a href="{{ route('admin.clients.create') }}">Add New Client</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.services.index') }}">Products/Services</a></li>
                    <li><a href="{{ route('admin.domains.index') }}">Domains</a></li>
                </ul>
            </li>

            {{-- Orders --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">Orders</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.orders.index') }}">List All Orders</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}">Pending</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'active']) }}">Active</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'fraud']) }}">Fraud</a></li>
                    <li><a href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}">Cancelled</a></li>
                </ul>
            </li>

            {{-- Billing --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">Billing</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'Paid']) }}">Paid Invoices</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'Unpaid']) }}">Unpaid Invoices</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'Overdue']) }}">Overdue Invoices</a></li>
                    <li><a href="{{ route('admin.invoices.index', ['status' => 'Cancelled']) }}">Cancelled Invoices</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.transactions') }}">Transactions</a></li>
                    <li><a href="{{ route('admin.config.billable-items') }}">Billable Items</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.quotes.index') }}">Quotes</a></li>
                </ul>
            </li>

            {{-- Support --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">Support</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.tickets.index') }}">Support Tickets</a></li>
                    <li><a href="{{ route('admin.tickets.index') }}">Open Ticket</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.announcements') }}">Announcements</a></li>
                    <li><a href="{{ route('admin.config.downloads') }}">Downloads</a></li>
                    <li><a href="{{ route('admin.config.knowledge-base') }}">Knowledge Base</a></li>
                    <li><a href="{{ route('admin.config.network-issues') }}">Network Issues</a></li>
                </ul>
            </li>

            {{-- Reports --}}
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.reports.index') }}">Reports</a>
            </li>

            {{-- Utilities --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">Utilities</a>
                <ul class="dropdown-menu">
                    <li><a href="{{ route('admin.config.automation') }}">Automation Status</a></li>
                    <li><a href="{{ route('admin.config.todo') }}">To-Do List</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.activity-log') }}">Activity Log</a></li>
                    <li><a href="{{ route('admin.logs.index') }}">System Logs</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.system-database') }}">System Database</a></li>
                    <li><a href="{{ route('admin.config.system-phpinfo') }}">PHP Info</a></li>
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
                <input type="text" name="search" class="form-control" placeholder="Search..."
                       onfocus="document.getElementById('intellisearch').classList.add('active')"
                       onblur="setTimeout(function(){ document.getElementById('intellisearch').classList.remove('active'); }, 200)">
            </form>
        </div>

        <ul style="list-style:none; margin:0; padding:0; display:flex; align-items:center; height:45px;">
            {{-- Pending Orders Badge --}}
            @if(($sidebarCounts->pending_orders ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" title="Pending Orders" style="position:relative;">
                    <i class="fas fa-shopping-cart"></i> <span class="nav-badge nav-badge-warning">{{ $sidebarCounts->pending_orders }}</span>
                </a>
            </li>
            @endif

            {{-- Overdue Invoices Badge --}}
            @if(($sidebarCounts->overdue_invoices ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.invoices.index', ['status' => 'Overdue']) }}" title="Overdue Invoices" style="position:relative;">
                    <i class="fas fa-dollar-sign"></i> <span class="nav-badge">{{ $sidebarCounts->overdue_invoices }}</span>
                </a>
            </li>
            @endif

            {{-- Open Tickets Badge --}}
            @if(($sidebarCounts->open_tickets ?? 0) > 0)
            <li style="float:left; width:auto;">
                <a href="{{ route('admin.tickets.index') }}" title="Open Tickets" style="position:relative;">
                    <i class="fas fa-ticket-alt"></i> <span class="nav-badge nav-badge-info">{{ $sidebarCounts->open_tickets }}</span>
                </a>
            </li>
            @endif

            {{-- Setup / Config --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();"><i class="fas fa-wrench"></i> Setup</a>
                <ul class="dropdown-menu" style="right:0; left:auto;">
                    <li><a href="{{ route('admin.config.admins') }}">Admin Accounts</a></li>
                    <li><a href="{{ route('admin.config.admin-roles') }}">Admin Roles</a></li>
                    <li><a href="{{ route('admin.config.api-credentials') }}">API Credentials</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.products.index') }}">Products/Services</a></li>
                    <li><a href="{{ route('admin.config.servers') }}">Servers</a></li>
                    <li><a href="{{ route('admin.config.server-groups') }}">Server Groups</a></li>
                    <li><a href="{{ route('admin.config.domain-pricing') }}">Domain Pricing</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.gateways') }}">Payment Gateways</a></li>
                    <li><a href="{{ route('admin.config.registrars') }}">Domain Registrars</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.currencies') }}">Currencies</a></li>
                    <li><a href="{{ route('admin.config.tax') }}">Tax Rules</a></li>
                    <li><a href="{{ route('admin.config.promotions') }}">Promotions</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.config.ticket-departments') }}">Ticket Departments</a></li>
                    <li><a href="{{ route('admin.config.ticket-statuses') }}">Ticket Statuses</a></li>
                    <li><a href="{{ route('admin.config.email-templates') }}">Email Templates</a></li>
                    <li class="divider"></li>
                    <li><a href="{{ route('admin.settings.general') }}">General Settings</a></li>
                    <li><a href="{{ route('admin.config.banned-ips') }}">Banned IPs</a></li>
                    <li><a href="{{ route('admin.config.banned-emails') }}">Banned Emails</a></li>
                    <li><a href="{{ route('admin.config.client-groups') }}">Client Groups</a></li>
                </ul>
            </li>

            {{-- User Menu --}}
            <li class="has-dropdown" style="float:left; width:auto; position:relative;">
                <a href="#" onclick="event.preventDefault();">
                    <i class="fas fa-user"></i> {{ auth('admin')->user()->full_name ?? 'Admin' }}
                </a>
                <ul class="dropdown-menu" style="right:0; left:auto;">
                    <li><a href="{{ route('admin.config.admins') }}">My Account</a></li>
                    <li><a href="/" target="_blank">Client Area</a></li>
                    <li class="divider"></li>
                    <li>
                        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>
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
        <div class="sidebar-header"><i class="fas fa-star"></i> Shortcuts</div>
        <ul class="menu">
            <li><a href="{{ route('admin.clients.create') }}">Add New Client</a></li>
            <li><a href="{{ route('admin.invoices.create') }}">Create Invoice</a></li>
            <li><a href="{{ route('admin.quotes.create') }}">Create Quote</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}">Pending Orders @if(($sidebarCounts->pending_orders ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->pending_orders }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'Overdue']) }}">Overdue Invoices @if(($sidebarCounts->overdue_invoices ?? 0) > 0)<span class="sb-badge">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index') }}">Open Tickets @if(($sidebarCounts->open_tickets ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->open_tickets }}</span>@endif</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-wrench"></i> System Overview</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.automation') }}">Automation Status</a></li>
            <li><a href="{{ route('admin.config.activity-log') }}">Activity Log</a></li>
            <li><a href="{{ route('admin.logs.index') }}">System Logs</a></li>
            <li><a href="{{ route('admin.config.system-phpinfo') }}">PHP Info</a></li>
        </ul>

    {{-- ── Clients Sidebar ── --}}
    @elseif(str_starts_with($segment, 'client'))
        <div class="sidebar-header"><i class="fas fa-user"></i> Clients</div>
        <ul class="menu">
            <li><a href="{{ route('admin.clients.index') }}" @if($routeName === 'admin.clients.index') class="active" @endif>View/Search Clients</a></li>
            <li><a href="{{ route('admin.clients.create') }}" @if($routeName === 'admin.clients.create') class="active" @endif>Add New Client</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-cube"></i> Services</div>
        <ul class="menu">
            <li><a href="{{ route('admin.services.index') }}" @if($routeName === 'admin.services.index') class="active" @endif>Products/Services</a></li>
            <li><a href="{{ route('admin.domains.index') }}" @if($routeName === 'admin.domains.index') class="active" @endif>Domains</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-coins"></i> Affiliates</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.affiliates') }}">Affiliate Accounts</a></li>
        </ul>

    {{-- ── Orders Sidebar ── --}}
    @elseif($segment === 'orders')
        <div class="sidebar-header"><i class="fas fa-cube"></i> Orders</div>
        <ul class="menu">
            <li><a href="{{ route('admin.orders.index') }}" @if(!request()->has('status')) class="active" @endif>All Orders</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" @if(request()->get('status') === 'pending') class="active" @endif>Pending @if(($sidebarCounts->pending_orders ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->pending_orders }}</span>@endif</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'active']) }}" @if(request()->get('status') === 'active') class="active" @endif>Active</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'fraud']) }}" @if(request()->get('status') === 'fraud') class="active" @endif>Fraud</a></li>
            <li><a href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}" @if(request()->get('status') === 'cancelled') class="active" @endif>Cancelled</a></li>
        </ul>

    {{-- ── Invoices / Billing Sidebar ── --}}
    @elseif($segment === 'invoices' || $segment === 'quotes' || $routeName === 'admin.config.transactions' || $routeName === 'admin.config.billable-items')
        <div class="sidebar-header"><i class="fas fa-money-bill-wave"></i> Billing</div>
        <ul class="menu">
            <li><a href="{{ route('admin.invoices.index') }}" @if($routeName === 'admin.invoices.index' && !request()->has('status')) class="active" @endif>All Invoices</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'Paid']) }}" @if(request()->get('status') === 'Paid') class="active" @endif>Paid</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'Unpaid']) }}" @if(request()->get('status') === 'Unpaid') class="active" @endif>Unpaid @if(($sidebarCounts->unpaid_invoices ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->unpaid_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'Overdue']) }}" @if(request()->get('status') === 'Overdue') class="active" @endif>Overdue @if(($sidebarCounts->overdue_invoices ?? 0) > 0)<span class="sb-badge">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a></li>
            <li><a href="{{ route('admin.invoices.index', ['status' => 'Cancelled']) }}" @if(request()->get('status') === 'Cancelled') class="active" @endif>Cancelled</a></li>
            <li><a href="{{ route('admin.invoices.create') }}" @if($routeName === 'admin.invoices.create') class="active" @endif>Create Invoice</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-coins"></i> Transactions</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.transactions') }}" @if($routeName === 'admin.config.transactions') class="active" @endif>Transactions</a></li>
            <li><a href="{{ route('admin.config.billable-items') }}" @if($routeName === 'admin.config.billable-items') class="active" @endif>Billable Items</a></li>
        </ul>
        <div class="sidebar-header"><i class="fas fa-file-signature"></i> Quotes</div>
        <ul class="menu">
            <li><a href="{{ route('admin.quotes.index') }}" @if($routeName === 'admin.quotes.index') class="active" @endif>Quotes</a></li>
            <li><a href="{{ route('admin.quotes.create') }}" @if($routeName === 'admin.quotes.create') class="active" @endif>Create Quote</a></li>
        </ul>

    {{-- ── Support / Tickets Sidebar ── --}}
    @elseif($segment === 'tickets')
        <div class="sidebar-header"><i class="fas fa-life-ring"></i> Support</div>
        <ul class="menu">
            <li><a href="{{ route('admin.tickets.index') }}" @if($routeName === 'admin.tickets.index' && !request()->has('status')) class="active" @endif>All Tickets @if(($sidebarCounts->active_tickets ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->active_tickets }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Open']) }}" @if(request()->get('status') === 'Open') class="active" @endif>Open @if(($sidebarCounts->open_tickets_only ?? 0) > 0)<span class="sb-badge sb-badge-info">{{ $sidebarCounts->open_tickets_only }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Customer-Reply']) }}" @if(request()->get('status') === 'Customer-Reply') class="active" @endif>Awaiting Reply @if(($sidebarCounts->awaiting_tickets ?? 0) > 0)<span class="sb-badge sb-badge-warning">{{ $sidebarCounts->awaiting_tickets }}</span>@endif</a></li>
            <li><a href="{{ route('admin.tickets.index', ['status' => 'Closed']) }}" @if(request()->get('status') === 'Closed') class="active" @endif>Closed</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-comments"></i> Filter Tickets</div>
        <div class="advanced-search">
            <form action="{{ route('admin.tickets.index') }}" method="GET">
                <select name="department">
                    <option value="">-- Department --</option>
                </select>
                <select name="status">
                    <option value="">-- Status --</option>
                    <option value="Open">Open</option>
                    <option value="Answered">Answered</option>
                    <option value="Customer-Reply">Customer Reply</option>
                    <option value="Closed">Closed</option>
                </select>
                <select name="priority">
                    <option value="">-- Priority --</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
                <button type="submit" class="btn-go">Filter</button>
            </form>
        </div>

        <div class="sidebar-header"><i class="fas fa-bullhorn"></i> Content</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.announcements') }}">Announcements</a></li>
            <li><a href="{{ route('admin.config.downloads') }}">Downloads</a></li>
            <li><a href="{{ route('admin.config.knowledge-base') }}">Knowledge Base</a></li>
            <li><a href="{{ route('admin.config.network-issues') }}">Network Issues</a></li>
        </ul>

    {{-- ── Reports Sidebar ── --}}
    @elseif($segment === 'reports')
        <div class="sidebar-header"><i class="fas fa-chart-bar"></i> Reports</div>
        <ul class="menu">
            <li><a href="{{ route('admin.reports.index') }}" class="active">Reports Overview</a></li>
        </ul>

    {{-- ── Config / Setup Sidebar ── --}}
    @elseif($segment === 'config' || $segment === 'settings' || $segment === 'products')
        <div class="sidebar-header"><i class="fas fa-users-cog"></i> Staff Management</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.admins') }}" @if($routeName === 'admin.config.admins') class="active" @endif>Administrator Accounts</a></li>
            <li><a href="{{ route('admin.config.admin-roles') }}" @if($routeName === 'admin.config.admin-roles') class="active" @endif>Administrator Roles</a></li>
            <li><a href="{{ route('admin.config.api-credentials') }}" @if($routeName === 'admin.config.api-credentials') class="active" @endif>API Credentials</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-credit-card"></i> Payments</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.gateways') }}" @if($routeName === 'admin.config.gateways') class="active" @endif>Payment Gateways</a></li>
            <li><a href="{{ route('admin.config.currencies') }}" @if($routeName === 'admin.config.currencies') class="active" @endif>Currencies</a></li>
            <li><a href="{{ route('admin.config.tax') }}" @if($routeName === 'admin.config.tax') class="active" @endif>Tax Rules</a></li>
            <li><a href="{{ route('admin.config.promotions') }}" @if($routeName === 'admin.config.promotions') class="active" @endif>Promotions</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-cube"></i> Products</div>
        <ul class="menu">
            <li><a href="{{ route('admin.products.index') }}" @if($routeName === 'admin.products.index') class="active" @endif>Products/Services</a></li>
            <li><a href="{{ route('admin.products.create') }}" @if($routeName === 'admin.products.create') class="active" @endif>Create Product</a></li>
            <li><a href="{{ route('admin.products.groups.create') }}" @if($routeName === 'admin.products.groups.create') class="active" @endif>Product Groups</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-server"></i> Servers &amp; Domains</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.servers') }}" @if($routeName === 'admin.config.servers') class="active" @endif>Servers</a></li>
            <li><a href="{{ route('admin.config.server-groups') }}" @if($routeName === 'admin.config.server-groups') class="active" @endif>Server Groups</a></li>
            <li><a href="{{ route('admin.config.domain-pricing') }}" @if($routeName === 'admin.config.domain-pricing') class="active" @endif>Domain Pricing</a></li>
            <li><a href="{{ route('admin.config.registrars') }}" @if($routeName === 'admin.config.registrars') class="active" @endif>Domain Registrars</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-life-ring"></i> Support</div>
        <ul class="menu">
            <li><a href="{{ route('admin.config.ticket-departments') }}" @if($routeName === 'admin.config.ticket-departments') class="active" @endif>Ticket Departments</a></li>
            <li><a href="{{ route('admin.config.ticket-statuses') }}" @if($routeName === 'admin.config.ticket-statuses') class="active" @endif>Ticket Statuses</a></li>
            <li><a href="{{ route('admin.config.email-templates') }}" @if($routeName === 'admin.config.email-templates') class="active" @endif>Email Templates</a></li>
        </ul>

        <div class="sidebar-header"><i class="fas fa-wrench"></i> Other</div>
        <ul class="menu">
            <li><a href="{{ route('admin.settings.general') }}" @if($routeName === 'admin.settings.general') class="active" @endif>General Settings</a></li>
            <li><a href="{{ route('admin.config.client-groups') }}" @if($routeName === 'admin.config.client-groups') class="active" @endif>Client Groups</a></li>
            <li><a href="{{ route('admin.config.banned-ips') }}" @if($routeName === 'admin.config.banned-ips') class="active" @endif>Banned IPs</a></li>
            <li><a href="{{ route('admin.config.banned-emails') }}" @if($routeName === 'admin.config.banned-emails') class="active" @endif>Banned Emails</a></li>
        </ul>

    {{-- ── Logs Sidebar ── --}}
    @elseif($segment === 'logs')
        <div class="sidebar-header"><i class="fas fa-file-signature"></i> Logs</div>
        <ul class="menu">
            <li><a href="{{ route('admin.logs.index') }}" @if($routeName === 'admin.logs.index') class="active" @endif>System Logs</a></li>
            <li><a href="{{ route('admin.logs.gateway') }}" @if($routeName === 'admin.logs.gateway') class="active" @endif>Gateway Logs</a></li>
            <li><a href="{{ route('admin.logs.module') }}" @if($routeName === 'admin.logs.module') class="active" @endif>Module Logs</a></li>
            <li><a href="{{ route('admin.logs.email') }}" @if($routeName === 'admin.logs.email') class="active" @endif>Email Logs</a></li>
        </ul>

    {{-- ── Default Sidebar (fallback) ── --}}
    @else
        <div class="sidebar-header"><i class="fas fa-star"></i> Quick Links</div>
        <ul class="menu">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.clients.index') }}">Clients</a></li>
            <li><a href="{{ route('admin.orders.index') }}">Orders</a></li>
            <li><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
            <li><a href="{{ route('admin.tickets.index') }}">Tickets</a></li>
            <li><a href="{{ route('admin.reports.index') }}">Reports</a></li>
        </ul>
    @endif

    {{-- ── Advanced Search (always visible) ── --}}
    <div class="sidebar-header"><i class="fas fa-binoculars"></i> Advanced Search</div>
    <div class="advanced-search">
        <form action="{{ route('admin.clients.index') }}" method="GET">
            <input type="text" name="search" placeholder="Client Name/Email...">
            <select name="search_type">
                <option value="clients">Clients</option>
                <option value="invoices">Invoices</option>
                <option value="services">Services</option>
                <option value="domains">Domains</option>
                <option value="tickets">Tickets</option>
            </select>
            <button type="submit" class="btn-go">Search</button>
        </form>
    </div>

    {{-- ── Staff Online ── --}}
    <div class="sidebar-header"><i class="fas fa-circle" style="color:#22c55e;font-size:8px;"></i> Staff Online</div>
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
    <h1>@yield('title', 'Dashboard')</h1>

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
<div class="footerbar clearfix">
    <div style="float:left;">
        &copy; {{ date('Y') }} PNLCS - Billing &amp; Support System
    </div>
    <div style="float:right;">
        <a href="{{ route('admin.dashboard') }}">Admin Home</a> |
        <a href="/" target="_blank">Client Area</a> |
        <a href="{{ route('admin.logs.index') }}">Logs</a>
    </div>
</div>

@vite(["resources/js/app.js"])
@stack('scripts')
</body>
</html>
