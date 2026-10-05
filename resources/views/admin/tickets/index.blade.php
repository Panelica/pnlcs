@extends("admin.layouts.app")
@section("title", __("admin.tickets.title"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.tickets.title') }}</h1>
    <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.tickets.open_ticket') }}</a>
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
    <a href="{{ request()->boolean('guests') ? route('admin.tickets.index', request()->except('guests', 'page')) : route('admin.tickets.index', array_merge(request()->except('page'), ['guests' => 1])) }}"
       style="margin-left:auto;align-self:center;font-size:12px;text-decoration:none;padding:4px 10px;border-radius:12px;border:1px solid {{ request()->boolean('guests') ? '#1a4d80' : '#ddd' }};color:{{ request()->boolean('guests') ? '#1a4d80' : '#666' }};">
        <i class="fas fa-user-slash"></i> {{ __('admin.tickets.only_guests') }}
    </a>
</div>

@php $canDelete = auth('admin')->user()?->hasPermission('manage_tickets'); @endphp

<!-- Table -->
<form id="ticket-bulk-form" method="POST" action="{{ route('admin.tickets.bulk-delete') }}">
@csrf
<div class="card">
    @if($canDelete)
    <div style="padding:10px 16px;border-bottom:1px solid #e5e7eb;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <strong style="font-size:12px;color:#777;margin-right:6px;">{{ __('admin.invoices.bulk_actions') }}:</strong>
        <button type="submit" class="btn btn-danger btn-sm" id="ticket-bulk-delete"><i class="fas fa-trash"></i> {{ __('admin.tickets.bulk_delete') }}</button>
    </div>
    @endif
    <table class="data-table">
        <thead>
            <tr>
                @if($canDelete)<th style="width:30px;"><input type="checkbox" id="ticket-select-all"></th>@endif
                <th>{{ __('admin.tickets.ticket_num') }}</th>
                <th>{{ __('common.table.department') }}</th>
                <th>{{ __('common.table.subject') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.priority') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.last_reply') }}</th>
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
                @if($canDelete)<td><input type="checkbox" name="ticket_ids[]" value="{{ $ticket->id }}" class="ticket-row-checkbox"></td>@endif
                <td><a href="{{ route("admin.tickets.show", $ticket) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $ticket->tid }}</a></td>
                <td style="color:#666;">{{ $ticket->department->name ?? "N/A" }}</td>
                <td><a href="{{ route("admin.tickets.show", $ticket) }}" style="color:#337ab7;text-decoration:none;font-weight:500;">{{ Str::limit($ticket->title, 55) }}</a></td>
                <td>{{ $ticket->client?->full_name ?? $ticket->name ?? $ticket->email }}@if(! $ticket->client_id) <span class="badge badge-cancelled" style="font-size:10px;">{{ __('admin.tickets.guest') }}</span>@endif</td>
                <td><span class="badge {{ $priorityBadge }}">{{ ucfirst($ticket->priority ?? "") }}</span></td>
                <td><span class="badge {{ $statusBadge }}">{{ ucfirst($ticket->status ?? "") }}</span></td>
                <td style="color:#666;font-size:12px;">{{ $ticket->last_reply?->diffForHumans() ?? "-" }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $canDelete ? 8 : 7 }}" style="text-align:center;padding:32px;color:#999;">{{ __('admin.tickets.no_tickets') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $tickets->withQueryString()->links() }}
    </div>
</div>
</form>

@if($canDelete)
<script>
document.getElementById('ticket-select-all').addEventListener('change', function () {
    document.querySelectorAll('.ticket-row-checkbox').forEach(function (cb) { cb.checked = this.checked; }, this);
});
document.getElementById('ticket-bulk-delete').addEventListener('click', function (e) {
    var count = document.querySelectorAll('.ticket-row-checkbox:checked').length;
    if (count === 0) {
        e.preventDefault();
        alert(@js(__('admin.tickets.select_none')));
        return;
    }
    if (! confirm(@js(__('admin.tickets.bulk_delete_confirm')).replace(':count', count))) {
        e.preventDefault();
    }
});
</script>
@endif

@endsection
