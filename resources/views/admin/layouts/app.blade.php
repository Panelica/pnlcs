<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Admin") - PNLCS</title>
    @vite(["resources/css/app.css"])
    <style>[x-cloak]{display:none!important;}</style>
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
        <a href="{{ route("admin.orders.index") }}" class="sb-link {{ request()->routeIs("admin.orders.*") ? "active" : "" }}"><span class="ico">&#128722;</span> All Orders @if($sidebarCounts->pending_orders > 0)<span class="sb-badge">{{ $sidebarCounts->pending_orders }}</span>@endif</a>
        <a href="{{ route("admin.orders.index") }}?status=pending" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Pending</a>
        <a href="{{ route("admin.orders.index") }}?status=fraud" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Fraud</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Billing</div>
        <a href="{{ route("admin.invoices.index") }}" class="sb-link {{ request()->routeIs("admin.invoices.*") ? "active" : "" }}"><span class="ico">&#128196;</span> Invoices @if(($sidebarCounts->unpaid_invoices + $sidebarCounts->overdue_invoices) > 0)<span class="sb-badge">{{ $sidebarCounts->unpaid_invoices + $sidebarCounts->overdue_invoices }}</span>@endif</a>
        <a href="{{ route("admin.invoices.index") }}?status=unpaid" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Unpaid</a>
        <a href="{{ route("admin.invoices.index") }}?status=overdue" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Overdue</a>
        <a href="{{ route("admin.config.transactions") }}" class="sb-link {{ request()->routeIs("admin.config.transactions") ? "active" : "" }}"><span class="ico">&#128178;</span> Transactions</a>
        <a href="{{ route("admin.quotes.index") }}" class="sb-link {{ request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") ? "active" : "" }}"><span class="ico">&#128203;</span> Quotes</a>
        <a href="{{ route("admin.config.billable-items") }}" class="sb-link {{ request()->routeIs("admin.config.billable-items") ? "active" : "" }}"><span class="ico">&#128179;</span> Billable Items</a>
        <a href="{{ route("admin.config.promotions") }}" class="sb-link {{ request()->routeIs("admin.config.promotions") ? "active" : "" }}"><span class="ico">&#127991;</span> Promotions</a>
    </div>
    <div class="sb-section">
        <div class="sb-hdr">Support</div>
        <a href="{{ route("admin.tickets.index") }}" class="sb-link {{ request()->routeIs("admin.tickets.*") ? "active" : "" }}"><span class="ico">&#127915;</span> Tickets @if($sidebarCounts->open_tickets > 0)<span class="sb-badge">{{ $sidebarCounts->open_tickets }}</span>@endif</a>
        <a href="{{ route("admin.tickets.index") }}?status=Open" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Open</a>
        <a href="{{ route("admin.tickets.index") }}?status=Customer-Reply" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; Awaiting Reply</a>
        <a href="{{ route("admin.tickets.index") }}?priority=High" class="sb-link" style="padding-left:28px;font-size:12px;color:rgba(255,255,255,0.5);">&#8627; High Priority</a>
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
        <div class="cs-section"><div class="cs-hdr">Quick Actions</div>
            <a href="{{ route("admin.clients.create") }}" class="cs-link"><span class="ico">&#43;</span> Add Client</a>
            <a href="{{ route("admin.invoices.create") }}" class="cs-link"><span class="ico">&#128196;</span> New Invoice</a>
            <a href="{{ route("admin.quotes.create") }}" class="cs-link"><span class="ico">&#128203;</span> New Quote</a>
            <a href="{{ route("admin.tickets.index") }}" class="cs-link"><span class="ico">&#127915;</span> Support Tickets @if($sidebarCounts->open_tickets)<span class="cs-badge cs-badge-danger">{{ $sidebarCounts->open_tickets }}</span>@endif</a>
            <a href="{{ route("admin.orders.index") }}?status=pending" class="cs-link"><span class="ico">&#9203;</span> Pending Orders @if($sidebarCounts->pending_orders)<span class="cs-badge cs-badge-warning">{{ $sidebarCounts->pending_orders }}</span>@endif</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Billing</div>
            <a href="{{ route("admin.invoices.index") }}?status=unpaid" class="cs-link">Unpaid Invoices @if($sidebarCounts->unpaid_invoices)<span class="cs-badge cs-badge-warning">{{ $sidebarCounts->unpaid_invoices }}</span>@endif</a>
            <a href="{{ route("admin.invoices.index") }}?status=overdue" class="cs-link">Overdue Invoices @if($sidebarCounts->overdue_invoices)<span class="cs-badge cs-badge-danger">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a>
            <a href="{{ route("admin.config.transactions") }}" class="cs-link">Transactions</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">System</div>
            <a href="{{ route("admin.reports.index") }}" class="cs-link">&#128202; Reports</a>
            <a href="{{ route("admin.config.activity-log") }}" class="cs-link">Activity Log</a>
            <a href="{{ route("admin.config.system-database") }}" class="cs-link">System Info</a>
            <a href="{{ route("admin.config.todo") }}" class="cs-link">To-Do List</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Staff Online</div>
            <div class="cs-staff-row"><span class="cs-staff-dot"></span>{{ auth("admin")->user()->full_name ?? "Admin" }}</div>
        </div>
    @elseif(request()->routeIs("admin.clients.*") || request()->routeIs("admin.services.*") || request()->routeIs("admin.domains.*"))
        <div class="cs-section"><div class="cs-hdr">Clients</div>
            <a href="{{ route("admin.clients.index") }}" class="cs-link {{ request()->routeIs("admin.clients.index") ? "active" : "" }}">All Clients</a>
            <a href="{{ route("admin.clients.index") }}?status=active" class="cs-link" style="padding-left:24px;font-size:12px;">&#8627; Active</a>
            <a href="{{ route("admin.clients.index") }}?status=inactive" class="cs-link" style="padding-left:24px;font-size:12px;">&#8627; Inactive</a>
            <a href="{{ route("admin.clients.create") }}" class="cs-link {{ request()->routeIs("admin.clients.create") ? "active" : "" }}">&#43; Add Client</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Products &amp; Services</div>
            <a href="{{ route("admin.services.index") }}" class="cs-link {{ request()->routeIs("admin.services.*") ? "active" : "" }}">All Services</a>
            <a href="{{ route("admin.services.index") }}?status=Active" class="cs-link" style="padding-left:24px;font-size:12px;">&#8627; Active</a>
            <a href="{{ route("admin.services.index") }}?status=Suspended" class="cs-link" style="padding-left:24px;font-size:12px;">&#8627; Suspended</a>
            <a href="{{ route("admin.domains.index") }}" class="cs-link {{ request()->routeIs("admin.domains.*") ? "active" : "" }}">Domains</a>
            <a href="{{ route("admin.domains.index") }}?status=Expired" class="cs-link" style="padding-left:24px;font-size:12px;">&#8627; Expired</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Search</div>
            <div class="cs-filter-form">
                <form method="GET" action="{{ route("admin.clients.index") }}">
                    <input type="text" name="search" placeholder="Name, email, company..." value="{{ request("search") }}">
                    <button type="submit" class="btn-go">Search Clients</button>
                </form>
            </div>
        </div>
    @elseif(request()->routeIs("admin.invoices.*") || request()->routeIs("admin.config.transactions") || request()->routeIs("admin.quotes.*") || request()->routeIs("admin.config.quotes") || request()->routeIs("admin.config.billable-items"))
        <div class="cs-section"><div class="cs-hdr">Invoices</div>
            <a href="{{ route("admin.invoices.index") }}" class="cs-link {{ request()->routeIs("admin.invoices.index") ? "active" : "" }}">All Invoices</a>
            <a href="{{ route("admin.invoices.index") }}?status=unpaid" class="cs-link">Unpaid @if($sidebarCounts->unpaid_invoices)<span class="cs-badge cs-badge-warning">{{ $sidebarCounts->unpaid_invoices }}</span>@endif</a>
            <a href="{{ route("admin.invoices.index") }}?status=overdue" class="cs-link">Overdue @if($sidebarCounts->overdue_invoices)<span class="cs-badge cs-badge-danger">{{ $sidebarCounts->overdue_invoices }}</span>@endif</a>
            <a href="{{ route("admin.invoices.index") }}?status=paid" class="cs-link">Paid <span class="cs-badge cs-badge-success" style="font-size:9px;">&#10003;</span></a>
            <a href="{{ route("admin.invoices.index") }}?status=cancelled" class="cs-link">Cancelled</a>
            <a href="{{ route("admin.invoices.create") }}" class="cs-link">&#43; New Invoice</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Other Billing</div>
            <a href="{{ route("admin.config.transactions") }}" class="cs-link {{ request()->routeIs("admin.config.transactions") ? "active" : "" }}">Transactions</a>
            <a href="{{ route("admin.quotes.index") }}" class="cs-link {{ request()->routeIs("admin.quotes.*") ? "active" : "" }}">Quotes</a>
            <a href="{{ route("admin.config.billable-items") }}" class="cs-link {{ request()->routeIs("admin.config.billable-items") ? "active" : "" }}">Billable Items</a>
            <a href="{{ route("admin.config.promotions") }}" class="cs-link">Promotions</a>
        </div>
    @elseif(request()->routeIs("admin.tickets.*"))
        <div class="cs-section"><div class="cs-hdr">Tickets</div>
            <a href="{{ route("admin.tickets.index") }}" class="cs-link {{ !request("status") && !request("dept") && !request("priority") ? "active" : "" }}">All Tickets @if($sidebarCounts->active_tickets)<span class="cs-badge cs-badge-secondary">{{ $sidebarCounts->active_tickets }}</span>@endif</a>
            <a href="{{ route("admin.tickets.index") }}?status=Open" class="cs-link {{ request("status")==="Open" ? "active" : "" }}">Open @if($sidebarCounts->open_tickets_only)<span class="cs-badge cs-badge-success">{{ $sidebarCounts->open_tickets_only }}</span>@endif</a>
            <a href="{{ route("admin.tickets.index") }}?status=Customer-Reply" class="cs-link {{ request("status")==="Customer-Reply" ? "active" : "" }}">Awaiting Reply @if($sidebarCounts->awaiting_tickets)<span class="cs-badge cs-badge-warning">{{ $sidebarCounts->awaiting_tickets }}</span>@endif</a>
            <a href="{{ route("admin.tickets.index") }}?status=Answered" class="cs-link {{ request("status")==="Answered" ? "active" : "" }}">Answered</a>
            <a href="{{ route("admin.tickets.index") }}?status=On+Hold" class="cs-link {{ request("status")==="On Hold" ? "active" : "" }}">On Hold</a>
            <a href="{{ route("admin.tickets.index") }}?status=In+Progress" class="cs-link {{ request("status")==="In Progress" ? "active" : "" }}">In Progress</a>
            <a href="{{ route("admin.tickets.index") }}?status=Closed" class="cs-link {{ request("status")==="Closed" ? "active" : "" }}">Closed</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Filter by Priority</div>
            <a href="{{ route("admin.tickets.index") }}?priority=High" class="cs-link {{ request("priority")==="High" ? "active" : "" }}"><span style="color:#ef4444;">&#9679;</span> High Priority @if($sidebarCounts->high_priority_tickets)<span class="cs-badge cs-badge-danger">{{ $sidebarCounts->high_priority_tickets }}</span>@endif</a>
            <a href="{{ route("admin.tickets.index") }}?priority=Medium" class="cs-link {{ request("priority")==="Medium" ? "active" : "" }}"><span style="color:#f59e0b;">&#9679;</span> Medium</a>
            <a href="{{ route("admin.tickets.index") }}?priority=Low" class="cs-link {{ request("priority")==="Low" ? "active" : "" }}"><span style="color:#22c55e;">&#9679;</span> Low</a>
        </div>
        <div class="cs-section"><div class="cs-hdr">Departments</div>
            @php $depts = \App\Models\TicketDepartment::orderBy("name")->get(); @endphp
            @foreach($depts as $dept)
            <a href="{{ route("admin.tickets.index") }}?dept={{ $dept->id }}" class="cs-link {{ request("dept") == $dept->id ? "active" : "" }}">{{ $dept->name }}</a>
            @endforeach
        </div>
        <div class="cs-section"><div class="cs-hdr">Content</div>
            <a href="{{ route("admin.config.announcements") }}" class="cs-link">Announcements</a>
            <a href="{{ route("admin.config.knowledge-base") }}" class="cs-link">Knowledge Base</a>
            <a href="{{ route("admin.config.network-issues") }}" class="cs-link">Network Issues</a>
            <a href="{{ route("admin.config.downloads") }}" class="cs-link">Downloads</a>
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
    {{-- ── ADVANCED SEARCH (all contexts) ── --}}
    <div class="cs-section">
        <div class="cs-hdr">Advanced Search</div>
        <div class="cs-filter-form">
            <form method="GET" id="adv-search-form" action="">
                <select name="_type" id="adv-search-type" onchange="updateAdvSearch()" style="margin-bottom:5px;">
                    <option value="{{ route("admin.clients.index") }}">Clients</option>
                    <option value="{{ route("admin.orders.index") }}">Orders</option>
                    <option value="{{ route("admin.invoices.index") }}">Invoices</option>
                    <option value="{{ route("admin.tickets.index") }}">Tickets</option>
                    <option value="{{ route("admin.services.index") }}">Services</option>
                    <option value="{{ route("admin.domains.index") }}">Domains</option>
                </select>
                <input type="text" name="search" placeholder="Search..." style="margin-bottom:5px;">
                <button type="submit" class="btn-go">&#128269; Search</button>
            </form>
        </div>
    </div>
    {{-- ── STAFF ONLINE ── --}}
    <div class="cs-section">
        <div class="cs-hdr">Staff Online</div>
        <div class="cs-staff-row"><span class="cs-staff-dot"></span>{{ auth("admin")->user()->full_name ?? "Admin" }}</div>
    </div>
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
function updateAdvSearch() {
    var sel = document.getElementById("adv-search-type");
    var form = document.getElementById("adv-search-form");
    if (sel && form) { form.action = sel.value; }
}
document.addEventListener("DOMContentLoaded", function() { updateAdvSearch(); });
</script>
@stack("scripts")
</body>
</html>
