@extends("admin.layouts.app")
@section("title", __("admin.products.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.services.title') }}</h1>
</div>

<!-- Status Filter Tabs -->
<div style="margin-bottom:16px;border-bottom:1px solid #ddd;display:flex;gap:0;flex-wrap:wrap;">
    @foreach(["" => "All", "active" => "Active", "suspended" => "Suspended", "terminated" => "Terminated", "pending" => "Pending", "cancelled" => "Cancelled"] as $val => $label)
    @php $isActive = (request("status","") == $val); @endphp
    <a href="{{ route("admin.services.index", ["status" => $val]) }}"
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
                <th>{{ __('common.table.id') }}</th>
                <th>{{ __('common.table.product') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.domain') }}</th>
                <th>{{ __('common.table.billing_cycle') }}</th>
                <th style="text-align:right;">{{ __('common.table.amount') }}</th>
                <th>{{ __('admin.services.next_due') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($services as $service)
            @php
            $badgeClass = match(strtolower($service->status ?? "")) {
                "active"     => "badge-active",
                "pending"    => "badge-pending",
                "suspended"  => "badge-suspended",
                "terminated" => "badge-terminated",
                "cancelled"  => "badge-cancelled",
                default      => "badge-cancelled",
            };
            @endphp
            <tr>
                <td style="color:#666;font-family:monospace;font-size:12px;">{{ $service->id }}</td>
                <td style="font-weight:500;">{{ $service->product?->name ?? "N/A" }}</td>
                <td>
                    @if($service->client)
                    <a href="{{ route("admin.clients.show", $service->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $service->client->full_name }}</a>
                    @else N/A @endif
                </td>
                <td style="font-family:monospace;font-size:12px;color:#666;">{{ $service->domain ?? "-" }}</td>
                <td style="color:#666;">{{ ucfirst($service->billing_cycle ?? "-") }}</td>
                <td style="text-align:right;font-weight:500;">{{ money_fmt($service->amount) }}</td>
                <td style="color:#666;">{{ $service->next_due_date?->format(date_fmt()) ?? "-" }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($service->status ?? "") }}</span></td>
                <td>
                    <a href="{{ route("admin.services.show", $service) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center;padding:32px;color:#999;">{{ __('admin.services.no_services') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $services->withQueryString()->links() }}
    </div>
</div>

@endsection
