@extends("admin.layouts.app")
@section("title", "Support Tickets")
@section("content")

<div class="page-header">
    <h1>Support Tickets</h1>
</div>

<!-- Status Filter Tabs -->
<div style="margin-bottom:16px;border-bottom:1px solid #ddd;display:flex;gap:0;flex-wrap:wrap;">
    @foreach(["" => "All", "open" => "Open", "answered" => "Answered", "customer-reply" => "Customer Reply", "closed" => "Closed", "on hold" => "On Hold"] as $val => $label)
    @php $isActive = (request("status","") == $val); @endphp
    <a href="{{ route("admin.tickets.index", ["status" => $val]) }}"
       style="display:inline-block;padding:8px 16px;font-size:13px;text-decoration:none;color:{{ $isActive ? "#1a4d80" : "#666" }};font-weight:{{ $isActive ? "700" : "400" }};border-bottom:{{ $isActive ? "3px solid #1a4d80" : "3px solid transparent" }};margin-bottom:-1px;">
        {{ $label }}
    </a>
    @endforeach
</div>

<!-- Table -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Ticket #</th>
                <th>Department</th>
                <th>Subject</th>
                <th>Client</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Last Reply</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $ticket)
            @php
            $statusBadge = match(strtolower($ticket->status ?? "")) {
                "open"            => "badge-open",
                "answered"        => "badge-answered",
                "customer-reply"  => "badge-customer-reply",
                "closed"          => "badge-terminated",
                "on hold"         => "badge-suspended",
                "in progress"     => "badge-pending",
                default           => "badge-cancelled",
            };
            $priorityBadge = match(strtolower($ticket->priority ?? "")) {
                "high", "critical" => "badge-terminated",
                "medium"           => "badge-pending",
                "low"              => "badge-active",
                default            => "badge-cancelled",
            };
            @endphp
            <tr>
                <td><a href="{{ route("admin.tickets.show", $ticket) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $ticket->tid }}</a></td>
                <td style="color:#666;">{{ $ticket->department->name ?? "N/A" }}</td>
                <td><a href="{{ route("admin.tickets.show", $ticket) }}" style="color:#337ab7;text-decoration:none;font-weight:500;">{{ Str::limit($ticket->title, 55) }}</a></td>
                <td>{{ $ticket->client->full_name ?? $ticket->name ?? $ticket->email }}</td>
                <td><span class="badge {{ $priorityBadge }}">{{ ucfirst($ticket->priority ?? "") }}</span></td>
                <td><span class="badge {{ $statusBadge }}">{{ ucfirst($ticket->status ?? "") }}</span></td>
                <td style="color:#666;font-size:12px;">{{ $ticket->last_reply?->diffForHumans() ?? "-" }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#999;">No tickets found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $tickets->withQueryString()->links() }}
    </div>
</div>

@endsection
