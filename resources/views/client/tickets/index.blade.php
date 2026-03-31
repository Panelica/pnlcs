@extends('client.layouts.app')
@section('title', 'Support Tickets')
@section('content')

<div class="page-header">
    <h1>Support Tickets</h1>
    <a href="{{ route('client.tickets.create') }}" class="btn btn-primary btn-sm">+ Open Ticket</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Department</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Last Reply</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                <tr style="cursor:pointer;" onclick="window.location='{{ route('client.tickets.show', $t) }}'">
                    <td style="font-family:monospace; font-size:12px;">
                        <a href="{{ route('client.tickets.show', $t) }}" style="color:#337ab7;">#{{ $t->tid }}</a>
                    </td>
                    <td style="color:#777;">{{ $t->department->name ?? '-' }}</td>
                    <td>
                        <a href="{{ route('client.tickets.show', $t) }}" style="color:#337ab7; font-weight:500;">{{ Str::limit($t->title, 60) }}</a>
                    </td>
                    <td><span class="badge badge-{{ strtolower(str_replace(' ', '-', $t->status)) }}">{{ ucfirst($t->status) }}</span></td>
                    <td style="color:#777; font-size:12px;">{{ $t->last_reply?->diffForHumans() ?? $t->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:32px; color:#999;">
                        No tickets yet. <a href="{{ route('client.tickets.create') }}" style="color:#337ab7;">Open one</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($tickets instanceof \Illuminate\Pagination\LengthAwarePaginator && $tickets->hasPages())
    <div style="margin-top:16px;">{{ $tickets->links() }}</div>
@endif

@endsection
