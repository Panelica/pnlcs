@extends('client.layouts.app')
@section('title', 'Dashboard')
@section('styles')
<style>
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
    .stat-card { display: block; text-decoration: none; color: inherit; }
    .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .stat-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; }
    .stat-icon svg { width: 20px; height: 20px; }
    .stat-icon.green { background: #dff0d8; color: #3c763d; }
    .stat-icon.blue { background: #d9edf7; color: #31708f; }
    .stat-icon.orange { background: #fcf8e3; color: #8a6d3b; }
    .stat-icon.red { background: #f2dede; color: #a94442; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }
    .card-header-actions { display: flex; align-items: center; justify-content: space-between; }
    .card-header-actions a { font-size: 12px; color: #337ab7; text-decoration: none; }
    .quick-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
</style>
@endsection
@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
    <h1 style="font-size:20px; font-weight:600; margin:0;">Welcome, {{ auth()->user()->first_name }}</h1>
    <div style="display:flex; gap:8px;">
        <a href="{{ route('client.tickets.create') }}" class="btn btn-primary btn-sm">Open Ticket</a>
        <a href="{{ route('client.store') }}" class="btn btn-default btn-sm">Order Service</a>
    </div>
</div>

{{-- Stat Cards --}}
<div class="stats-grid">
    <a href="{{ route('client.services.index') }}" class="stat-card">
        <div class="stat-card">
            <div class="stat-icon green">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
            </div>
            <div class="stat-value" style="color:#3c763d;">{{ $serviceCount }}</div>
            <div class="stat-label">Active Services</div>
        </div>
    </a>
    <a href="{{ route('client.domains.index') }}" class="stat-card">
        <div class="stat-card">
            <div class="stat-icon blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
            </div>
            <div class="stat-value" style="color:#31708f;">{{ $domainCount }}</div>
            <div class="stat-label">Active Domains</div>
        </div>
    </a>
    <a href="{{ route('client.invoices.index') }}" class="stat-card">
        <div class="stat-card">
            <div class="stat-icon orange">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="stat-value" style="{{ $unpaidInvoices > 0 ? 'color:#8a6d3b;' : '' }}">{{ $unpaidInvoices }}</div>
            <div class="stat-label">Unpaid Invoices</div>
        </div>
    </a>
    <a href="{{ route('client.tickets.index') }}" class="stat-card">
        <div class="stat-card">
            <div class="stat-icon red">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            </div>
            <div class="stat-value" style="{{ $openTickets > 0 ? 'color:#a94442;' : '' }}">{{ $openTickets }}</div>
            <div class="stat-label">Open Tickets</div>
        </div>
    </a>
</div>

<div class="two-col">
    {{-- Recent Invoices --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-actions">
                <span>Recent Invoices</span>
                <a href="{{ route('client.invoices.index') }}">View all &rarr;</a>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            @if($recentInvoices->isEmpty())
                <div style="padding:24px; text-align:center; color:#999; font-size:13px;">No invoices yet.</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentInvoices as $invoice)
                        <tr>
                            <td><a href="{{ route('client.invoices.show', $invoice) }}" style="color:#337ab7;">#{{ $invoice->invoice_num ?? $invoice->id }}</a></td>
                            <td style="color:#777;">{{ $invoice->due_date?->format('d M Y') ?? '-' }}</td>
                            <td style="font-weight:500;">${{ number_format($invoice->total, 2) }}</td>
                            <td><span class="badge badge-{{ strtolower($invoice->status) }}">{{ ucfirst($invoice->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Recent Tickets --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-actions">
                <span>Recent Tickets</span>
                <a href="{{ route('client.tickets.index') }}">View all &rarr;</a>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            @if($recentTickets->isEmpty())
                <div style="padding:24px; text-align:center; color:#999; font-size:13px;">No tickets yet.</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Last Reply</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTickets as $ticket)
                        <tr>
                            <td><a href="{{ route('client.tickets.show', $ticket) }}" style="color:#337ab7;">{{ Str::limit($ticket->title, 35) }}</a></td>
                            <td><span class="badge badge-{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ ucfirst($ticket->status) }}</span></td>
                            <td style="color:#777; font-size:12px;">{{ $ticket->last_reply?->diffForHumans() ?? $ticket->created_at?->diffForHumans() }}</td>
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
<div class="card">
    <div class="card-header">
        <div class="card-header-actions">
            <span>Active Services</span>
            <a href="{{ route('client.services.index') }}">View all &rarr;</a>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Domain</th>
                    <th>Amount</th>
                    <th>Next Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activeServices as $service)
                <tr>
                    <td><a href="{{ route('client.services.show', $service) }}" style="color:#337ab7; font-weight:500;">{{ $service->product->name ?? 'Service #'.$service->id }}</a></td>
                    <td style="color:#777;">{{ $service->domain ?? '-' }}</td>
                    <td>${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</td>
                    <td style="color:#777;">{{ $service->next_due_date?->format('d M Y') ?? 'N/A' }}</td>
                    <td><span class="badge badge-active">Active</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Quick Actions --}}
<div class="quick-actions">
    <a href="{{ route('client.tickets.create') }}" class="btn btn-default">&#128101; Open Support Ticket</a>
    <a href="#" class="btn btn-default">&#127760; Register Domain</a>
    <a href="{{ route('client.store') }}" class="btn btn-default">&#128722; Order New Service</a>
    <a href="{{ route('client.funds.index') }}" class="btn btn-default">&#128176; Add Funds</a>
</div>

@endsection
