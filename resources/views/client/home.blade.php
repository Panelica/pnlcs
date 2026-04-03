@extends("client.layouts.app")
@section("title", "Dashboard")
@section("content")

{{-- Welcome Banner --}}
<div class="pn-welcome">
    <div class="pn-welcome-text">
        <h2>Welcome back, {{ auth()->user()->first_name }}!</h2>
        <p>Here is an overview of your account activity.</p>
    </div>
    @php $credit = auth()->user()->credit ?? 0; @endphp
    @if($credit > 0)
    <div class="pn-welcome-credit">
        <div class="credit-lbl">Account Credit</div>
        <div class="credit-val">${{ number_format($credit, 2) }}</div>
    </div>
    @endif
</div>

{{-- Stat Cards --}}
<div class="pn-stat-grid">
    <a href="{{ route("client.services.index") }}" class="pn-card pn-stat">
        <div class="pn-stat-icon pn-stat-icon-blue">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
        <div class="pn-stat-val">{{ $serviceCount }}</div>
        <div class="pn-stat-lbl">Active Services</div>
    </a>
    <a href="{{ route("client.domains.index") }}" class="pn-card pn-stat">
        <div class="pn-stat-icon pn-stat-icon-green">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
        </div>
        <div class="pn-stat-val">{{ $domainCount }}</div>
        <div class="pn-stat-lbl">Registered Domains</div>
    </a>
    <a href="{{ route("client.invoices.index") }}" class="pn-card pn-stat">
        <div class="pn-stat-icon {{ $unpaidInvoices > 0 ? "pn-stat-icon-orange" : "pn-stat-icon-green" }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="pn-stat-val" style="{{ $unpaidInvoices > 0 ? "color:var(--warning)" : "" }}">{{ $unpaidInvoices }}</div>
        <div class="pn-stat-lbl">Unpaid Invoices</div>
    </a>
    <a href="{{ route("client.tickets.index") }}" class="pn-card pn-stat">
        <div class="pn-stat-icon {{ $openTickets > 0 ? "pn-stat-icon-red" : "pn-stat-icon-green" }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        </div>
        <div class="pn-stat-val" style="{{ $openTickets > 0 ? "color:var(--danger)" : "" }}">{{ $openTickets }}</div>
        <div class="pn-stat-lbl">Open Tickets</div>
    </a>
</div>

{{-- Quick Actions --}}
<div class="pn-card mb-24" style="padding:20px 24px;">
    <div class="pn-section-title">Quick Actions</div>
    <div class="pn-actions">
        <a href="{{ route("client.store") }}" class="btn btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Order Service
        </a>
        <a href="{{ route("client.tickets.create") }}" class="btn btn-outline">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            Open Ticket
        </a>
        <a href="{{ route("client.funds.index") }}" class="btn btn-outline">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Funds
        </a>
        <a href="{{ route("client.account.profile") }}" class="btn btn-outline">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Edit Profile
        </a>
    </div>
</div>

{{-- Recent Invoices + Tickets --}}
<div class="pn-2col">
    <div class="pn-card">
        <div class="pn-card-header">
            <span class="pn-card-title">Recent Invoices</span>
            <a href="{{ route("client.invoices.index") }}" class="link text-sm">View all &rarr;</a>
        </div>
        <div class="pn-card-body-flush">
            @if($recentInvoices->isEmpty())
                <div class="pn-empty"><div class="pn-empty-icon">&#128196;</div><p>No invoices yet.</p></div>
            @else
            <table class="pn-table">
                <thead><tr><th>Invoice</th><th>Due</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($recentInvoices as $invoice)
                    <tr>
                        <td><a href="{{ route("client.invoices.show", $invoice) }}">#{{ $invoice->invoice_num ?? $invoice->id }}</a></td>
                        <td class="text-muted text-sm">{{ $invoice->due_date?->format("d M Y") ?? "-" }}</td>
                        <td style="font-weight:600">${{ number_format($invoice->total, 2) }}</td>
                        <td><span class="badge badge-{{ strtolower($invoice->status) }}">{{ ucfirst($invoice->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    <div class="pn-card">
        <div class="pn-card-header">
            <span class="pn-card-title">Recent Tickets</span>
            <a href="{{ route("client.tickets.index") }}" class="link text-sm">View all &rarr;</a>
        </div>
        <div class="pn-card-body-flush">
            @if($recentTickets->isEmpty())
                <div class="pn-empty"><div class="pn-empty-icon">&#128101;</div><p>No tickets yet.</p></div>
            @else
            <table class="pn-table">
                <thead><tr><th>Subject</th><th>Status</th><th>Last Reply</th></tr></thead>
                <tbody>
                    @foreach($recentTickets as $ticket)
                    <tr>
                        <td><a href="{{ route("client.tickets.show", $ticket) }}">{{ Str::limit($ticket->title, 38) }}</a></td>
                        <td><span class="badge badge-{{ strtolower(str_replace(" ", "-", $ticket->status)) }}">{{ ucfirst($ticket->status) }}</span></td>
                        <td class="text-muted text-sm">{{ $ticket->last_reply?->diffForHumans() ?? $ticket->created_at?->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- Active Services --}}
@if($activeServices->isNotEmpty())
<div class="pn-card">
    <div class="pn-card-header">
        <span class="pn-card-title">Active Services</span>
        <a href="{{ route("client.services.index") }}" class="link text-sm">View all &rarr;</a>
    </div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead><tr><th>Product</th><th>Domain</th><th>Amount</th><th>Next Due</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($activeServices as $service)
                <tr>
                    <td><a href="{{ route("client.services.show", $service) }}">{{ $service->product?->name ?? "Service #".$service->id }}</a></td>
                    <td class="text-muted">{{ $service->domain ?? "-" }}</td>
                    <td style="font-weight:600">${{ number_format($service->amount, 2) }}<span class="text-muted text-sm">/{{ $service->billing_cycle }}</span></td>
                    <td class="text-muted text-sm">{{ $service->next_due_date?->format("d M Y") ?? "N/A" }}</td>
                    <td><span class="badge badge-active">Active</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
