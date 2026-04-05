@extends("admin.layouts.app")

@section("title", "SSL Orders")

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SSL Certificate Orders</h1>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.ssl.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search domain, client..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['Awaiting Configuration', 'Configuration Submitted', 'Awaiting Issuance', 'Completed', 'Cancelled', 'Revoked', 'Expired'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Domain</th>
                    <th>Type</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Order Date</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>
                        <strong>{{ $order->domain ?: '—' }}</strong>
                        @if($order->cert_type)
                            <br><small class="text-muted">{{ $order->cert_type }}</small>
                        @endif
                    </td>
                    <td>{{ $order->module ?: '—' }}</td>
                    <td>
                        @if($order->client)
                            <a href="{{ route('admin.clients.show', $order->client_id) }}">
                                {{ $order->client->first_name }} {{ $order->client->last_name }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td><span class="badge {{ $order->getStatusBadgeClass() }}">{{ $order->status }}</span></td>
                    <td>{{ $order->order_date?->format('d M Y') ?: $order->created_at->format('d M Y') }}</td>
                    <td>
                        @if($order->crt_expires)
                            {{ $order->crt_expires->format('d M Y') }}
                            @if($order->daysUntilExpiry() !== null && $order->daysUntilExpiry() <= 30 && $order->daysUntilExpiry() > 0)
                                <br><small class="text-warning">{{ $order->daysUntilExpiry() }} days left</small>
                            @elseif($order->daysUntilExpiry() !== null && $order->daysUntilExpiry() <= 0)
                                <br><small class="text-danger">Expired</small>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.ssl.show', $order) }}" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No SSL orders found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@endsection
