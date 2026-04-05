@extends("client.layouts.app")
@section("title", __("client.domains.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">My Domains</h1>
        <p class="pn-page-subtitle">Manage your registered domain names.</p>
    </div>
    <a href="#" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Register Domain
    </a>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>Domain Name</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th>Registration Date</th>
                    <th>{{ __('common.table.expiry_date') }}</th>
                    <th>Auto-Renew</th>
                </tr>
            </thead>
            <tbody>
                @forelse($domains as $d)
                @php
                    $expiringSoon = $d->expiry_date && $d->expiry_date->diffInDays(now()) <= 30 && $d->expiry_date->isFuture();
                    $expired = $d->expiry_date && $d->expiry_date->isPast();
                @endphp
                <tr>
                    <td style="font-weight:600">{{ $d->domain }}</td>
                    <td><span class="badge badge-{{ strtolower($d->status) }}">{{ ucfirst($d->status) }}</span></td>
                    <td class="text-muted text-sm">{{ $d->registration_date?->format("d M Y") ?? "-" }}</td>
                    <td style="{{ $expired ? "color:var(--danger);font-weight:600" : ($expiringSoon ? "color:var(--warning);font-weight:600" : "") }}">
                        {{ $d->expiry_date?->format("d M Y") ?? "-" }}
                        @if($expired) <span class="badge badge-overdue" style="margin-left:6px">Expired</span>
                        @elseif($expiringSoon) <span class="badge badge-pending" style="margin-left:6px">Expiring</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ ($d->auto_renew ?? false) ? "badge-active" : "badge-no" }}">
                            {{ ($d->auto_renew ?? false) ? "Yes" : "No" }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#127760;</div>
                            <p>{{ __('admin.domains.no_domains') }}</p>
                            <a href="#" class="btn btn-primary">Register a Domain</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($domains instanceof \Illuminate\Pagination\LengthAwarePaginator && $domains->hasPages())
    <div class="mt-16">{{ $domains->links() }}</div>
@endif

@endsection
