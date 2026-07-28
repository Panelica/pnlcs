@extends("admin.layouts.app")

@section("title", __("admin.ssl_orders"))

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">{{ __('admin.ssl.title') }}</h1>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.ssl.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="{{ __('admin.ssl.search_placeholder') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">{{ __('common.misc.all_statuses') }}</option>
                    @foreach(['Awaiting Configuration', 'Configuration Submitted', 'Awaiting Issuance', 'Completed', 'Cancelled', 'Revoked', 'Expired'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">{{ __('common.actions.filter') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('common.table.id') }}</th>
                    <th>{{ __('common.table.domain') }}</th>
                    <th>{{ __('common.table.type') }}</th>
                    <th>{{ __('common.table.client') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th>{{ __('admin.ssl.order_date') }}</th>
                    <th>{{ __('admin.ssl.expires') }}</th>
                    <th>{{ __('common.table.actions') }}</th>
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
                    <td>{{ $order->order_date?->format(date_fmt()) ?: $order->created_at->format(date_fmt()) }}</td>
                    <td>
                        @if($order->crt_expires)
                            {{ $order->crt_expires->format(date_fmt()) }}
                            @if($order->daysUntilExpiry() !== null && $order->daysUntilExpiry() <= 30 && $order->daysUntilExpiry() > 0)
                                <br><small class="text-warning">{{ $order->daysUntilExpiry() }} {{ __('admin.ssl.days') }}</small>
                            @elseif($order->daysUntilExpiry() !== null && $order->daysUntilExpiry() <= 0)
                                <br><small class="text-danger">{{ __('admin.ssl.expired') }}</small>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.ssl.show', $order) }}" class="btn btn-sm btn-info">{{ __('common.actions.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">{{ __('admin.ssl.no_orders') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@endsection
