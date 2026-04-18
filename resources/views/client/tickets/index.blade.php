@extends("client.layouts.app")
@section("title", __("admin.tickets.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.tickets.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.tickets.page_subtitle') }}</p>
    </div>
    <a href="{{ route("client.tickets.create") }}" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('client.tickets.open_new') }}
    </a>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('client.tickets.ticket_prefix') }}</th>
                    <th>{{ __('common.table.department') }}</th>
                    <th>{{ __('common.table.subject') }}</th>
                    <th>{{ __('common.table.priority') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th>{{ __('common.table.last_reply') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                <tr style="cursor:pointer" onclick="window.location={{ json_encode(route("client.tickets.show", $t)) }}">
                    <td><a href="{{ route("client.tickets.show", $t) }}" style="font-family:monospace;font-size:13px;font-weight:600">#{{ $t->tid }}</a></td>
                    <td class="text-muted text-sm">{{ $t->department->name ?? "-" }}</td>
                    <td><a href="{{ route("client.tickets.show", $t) }}" style="font-weight:500">{{ Str::limit($t->title, 55) }}</a></td>
                    <td><span class="badge badge-{{ strtolower($t->priority ?? "medium") }}">{{ __('client.status.' . strtolower($t->priority ?? 'medium')) }}</span></td>
                    <td><span class="badge badge-{{ strtolower(str_replace(" ", "-", $t->status)) }}">{{ __('client.status.' . strtolower(str_replace(' ', '-', $t->status))) }}</span></td>
                    <td class="text-muted text-sm">{{ $t->last_reply?->diffForHumans() ?? $t->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128101;</div>
                            <p>{{ __('client.tickets.no_tickets') }}</p>
                            <a href="{{ route("client.tickets.create") }}" class="btn btn-primary">{{ __('client.tickets.open_first') }}</a>
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
