@extends("admin.layouts.app")
@section("title", "Dashboard")
@section("content")

<!-- Stat Cards Row -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:16px;margin-bottom:24px;">

    <div class="stat-card" style="border-left:4px solid #337ab7;">
        <div class="stat-value" style="color:#337ab7;">{{ $totalClients }}</div>
        <div class="stat-label">Total Clients</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #46a546;">
        <div class="stat-value" style="color:#46a546;">{{ $activeServices }}</div>
        <div class="stat-label">Active Services</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #008b8b;">
        <div class="stat-value" style="color:#008b8b;">{{ $activeDomains }}</div>
        <div class="stat-label">Active Domains</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #f89406;">
        <div class="stat-value" style="color:#f89406;">{{ $pendingOrders }}</div>
        <div class="stat-label">Pending Orders</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #c43c35;">
        <div class="stat-value" style="color:#c43c35;">{{ $openTickets }}</div>
        <div class="stat-label">Open Tickets</div>
    </div>

    <div class="stat-card" style="border-left:4px solid #d68100;">
        <div class="stat-value" style="color:#d68100;">{{ $unpaidInvoices }}</div>
        <div class="stat-label">Unpaid Invoices</div>
    </div>

</div>

<!-- Income Overview -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        Income Overview
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;">
        <div style="text-align:center;padding:16px;border-right:1px solid #e5e7eb;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#999;margin-bottom:6px;">This Month</div>
            <div style="font-size:24px;font-weight:700;color:#46a546;">${{ number_format($monthIncome, 2) }}</div>
        </div>
        <div style="text-align:center;padding:16px;border-right:1px solid #e5e7eb;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#999;margin-bottom:6px;">This Week</div>
            <div style="font-size:24px;font-weight:700;color:#337ab7;">${{ number_format($weekIncome, 2) }}</div>
        </div>
        <div style="text-align:center;padding:16px;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#999;margin-bottom:6px;">Today</div>
            <div style="font-size:24px;font-weight:700;color:#1a4d80;">${{ number_format($todayIncome, 2) }}</div>
        </div>
    </div>
</div>

<!-- 3-column Activity Grid -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">

    <!-- Recent Clients -->
    <div class="card">
        <div class="card-header">
            Recent Clients
            <a href="{{ route("admin.clients.index") }}" style="font-size:12px;font-weight:400;color:#337ab7;text-decoration:none;">View All</a>
        </div>
        <div>
            @forelse($recentClients as $client)
            <a href="{{ route("admin.clients.show", $client) }}" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:inherit;">
                <div style="width:30px;height:30px;background:#1a4d80;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;">{{ strtoupper(substr($client->first_name, 0, 1)) }}</div>
                <div style="min-width:0;">
                    <div style="font-size:13px;font-weight:500;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $client->full_name }}</div>
                    <div style="font-size:11px;color:#999;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $client->email }}</div>
                </div>
            </a>
            @empty
            <p style="padding:16px;font-size:13px;color:#999;margin:0;">No clients yet.</p>
            @endforelse
        </div>
    </div>

    <!-- Recent Tickets -->
    <div class="card">
        <div class="card-header">
            Recent Tickets
            <a href="{{ route("admin.tickets.index") }}" style="font-size:12px;font-weight:400;color:#337ab7;text-decoration:none;">View All</a>
        </div>
        <div>
            @forelse($recentTickets as $ticket)
            <a href="{{ route("admin.tickets.show", $ticket) }}" style="display:block;padding:10px 16px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:inherit;">
                <div style="font-size:13px;font-weight:500;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">#{{ $ticket->tid }} {{ $ticket->title }}</div>
                <div style="font-size:11px;color:#999;margin-top:2px;">{{ $ticket->department->name ?? "" }} &middot; {{ ucfirst($ticket->status) }}</div>
            </a>
            @empty
            <p style="padding:16px;font-size:13px;color:#999;margin:0;">No tickets yet.</p>
            @endforelse
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            Recent Orders
            <a href="{{ route("admin.orders.index") }}" style="font-size:12px;font-weight:400;color:#337ab7;text-decoration:none;">View All</a>
        </div>
        <div>
            @forelse($recentOrders as $order)
            <a href="{{ route("admin.orders.show", $order) }}" style="display:block;padding:10px 16px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:inherit;">
                <div style="font-size:13px;font-weight:500;color:#333;">#{{ $order->order_num }}</div>
                <div style="font-size:11px;color:#999;margin-top:2px;">{{ $order->client->full_name ?? "N/A" }} &middot; ${{ number_format($order->amount, 2) }} &middot; {{ ucfirst($order->status) }}</div>
            </a>
            @empty
            <p style="padding:16px;font-size:13px;color:#999;margin:0;">No orders yet.</p>
            @endforelse
        </div>
    </div>

</div>

<!-- 2-column: System Info + Quick Actions -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

    <!-- System Information -->
    <div class="card">
        <div class="card-header">System Information</div>
        <div class="card-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:6px 0;color:#666;width:50%;">PNLCS Version</td>
                    <td style="padding:6px 0;font-weight:500;">1.0.0</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:6px 0;color:#666;">Laravel</td>
                    <td style="padding:6px 0;font-weight:500;">{{ app()->version() }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:6px 0;color:#666;">PHP</td>
                    <td style="padding:6px 0;font-weight:500;">{{ phpversion() }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:6px 0;color:#666;">Database</td>
                    <td style="padding:6px 0;font-weight:500;">MySQL {{ DB::selectOne("SELECT VERSION() as v")->v }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#666;">Server</td>
                    <td style="padding:6px 0;font-weight:500;">{{ php_uname("n") }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">Quick Actions</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <a href="{{ route("admin.clients.create") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Add Client
                </a>
                <a href="{{ route("admin.products.create") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    Add Product
                </a>
                <a href="{{ route("admin.invoices.index") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Invoices
                </a>
                <a href="{{ route("admin.tickets.index") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v10H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                    Tickets
                </a>
                <a href="{{ route("admin.domains.index") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    Domains
                </a>
                <a href="{{ route("admin.settings.general") }}" class="btn btn-default" style="justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                    Settings
                </a>
            </div>
        </div>
    </div>

</div>

@endsection
