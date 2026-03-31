@extends("client.layouts.app")
@section("title", "Support Tickets")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Support Tickets</h1>
        <p class="pn-page-subtitle">Track the status of your support requests.</p>
    </div>
    <a href="{{ route("client.tickets.create") }}" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Open New Ticket
    </a>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Department</th>
                    <th>Subject</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Last Reply</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                <tr style="cursor:pointer" onclick="window.location={{ json_encode(route("client.tickets.show", $t)) }}">
                    <td><a href="{{ route("client.tickets.show", $t) }}" style="font-family:monospace;font-size:13px;font-weight:600">#{{ $t->tid }}</a></td>
                    <td class="text-muted text-sm">{{ $t->department->name ?? "-" }}</td>
                    <td><a href="{{ route("client.tickets.show", $t) }}" style="font-weight:500">{{ Str::limit($t->title, 55) }}</a></td>
                    <td><span class="badge badge-{{ strtolower($t->priority ?? "medium") }}">{{ ucfirst($t->priority ?? "Medium") }}</span></td>
                    <td><span class="badge badge-{{ strtolower(str_replace(" ", "-", $t->status)) }}">{{ ucfirst($t->status) }}</span></td>
                    <td class="text-muted text-sm">{{ $t->last_reply?->diffForHumans() ?? $t->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128101;</div>
                            <p>No support tickets yet.</p>
                            <a href="{{ route("client.tickets.create") }}" class="btn btn-primary">Open Your First Ticket</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($tickets instanceof \Illuminate\Pagination\LengthAwarePaginator && $tickets->hasPages())
    <div class="mt-16">{{ $tickets->links() }}</div>
@endif

@endsection
