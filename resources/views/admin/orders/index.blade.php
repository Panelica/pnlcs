@extends("admin.layouts.app")
@section("title", __("admin.orders.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.orders.title') }}</h1>
</div>

<!-- Status Filter Tabs -->
<div style="margin-bottom:16px;border-bottom:1px solid #ddd;display:flex;gap:0;flex-wrap:wrap;">
    @foreach(["" => "All", "pending" => "Pending", "active" => "Active", "fraud" => "Fraud", "cancelled" => "Cancelled"] as $val => $label)
    @php $isActive = (request("status","") == $val); @endphp
    <a href="{{ route("admin.orders.index", ["status" => $val]) }}"
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
                <th>{{ __('common.table.order_num') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.date') }}</th>
                <th style="text-align:right;">{{ __('common.table.amount') }}</th>
                <th>{{ __('common.table.payment_method') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php
            $badgeClass = match(strtolower($order->status ?? "")) {
                "active"    => "badge-active",
                "pending"   => "badge-pending",
                "fraud"     => "badge-fraud",
                "cancelled" => "badge-cancelled",
                default     => "badge-cancelled",
            };
            @endphp
            <tr>
                <td><a href="{{ route("admin.orders.show", $order) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $order->order_num }}</a></td>
                <td>
                    @if($order->client)
                    <a href="{{ route("admin.clients.show", $order->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $order->client?->full_name ?? "Deleted Client" }}</a>
                    @else N/A @endif
                </td>
                <td style="color:#666;">{{ $order->date?->format("d M Y") ?? "-" }}</td>
                <td style="text-align:right;font-weight:500;">${{ number_format($order->amount, 2) }}</td>
                <td style="color:#666;">{{ $order->payment_method ?? "-" }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($order->status ?? "") }}</span></td>
                <td>
                    <a href="{{ route("admin.orders.show", $order) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#999;">{{ __('admin.orders.no_orders') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>

@endsection
