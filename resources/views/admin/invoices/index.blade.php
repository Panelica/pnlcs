@extends("admin.layouts.app")
@section("title", __("admin.invoices.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.invoices.title') }}</h1>
    <a href="{{ route("admin.invoices.create") }}" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('admin.invoices.create_invoice') }}
    </a>
</div>

<!-- Status Filter Tabs -->
<div style="margin-bottom:16px;border-bottom:1px solid #ddd;display:flex;gap:0;flex-wrap:wrap;">
    @foreach(["" => "All", "unpaid" => "Unpaid", "paid" => "Paid", "overdue" => "Overdue", "cancelled" => "Cancelled", "draft" => "Draft"] as $val => $label)
    @php $isActive = (request("status","") == $val); @endphp
    <a href="{{ route("admin.invoices.index", ["status" => $val]) }}"
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
                <th>{{ __('common.table.invoice_num') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.date') }}</th>
                <th>{{ __('common.table.due_date') }}</th>
                <th style="text-align:right;">{{ __('common.table.total') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $invoice)
            @php
            $badgeClass = match(strtolower($invoice->status ?? "")) {
                "active", "paid"     => "badge-paid",
                "pending"            => "badge-pending",
                "unpaid"             => "badge-unpaid",
                "overdue"            => "badge-overdue",
                "suspended"          => "badge-suspended",
                "terminated"         => "badge-terminated",
                "cancelled"          => "badge-cancelled",
                "fraud"              => "badge-fraud",
                "draft"              => "badge-draft",
                "refunded"           => "badge-refunded",
                default              => "badge-cancelled",
            };
            @endphp
            <tr>
                <td><a href="{{ route("admin.invoices.show", $invoice) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $invoice->id }}</a></td>
                <td>
                    @if($invoice->client)
                    <a href="{{ route("admin.clients.show", $invoice->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $invoice->client->full_name }}</a>
                    @else N/A @endif
                </td>
                <td style="color:#666;">{{ $invoice->date?->format("d M Y") ?? "-" }}</td>
                <td style="color:#666;">{{ $invoice->due_date?->format("d M Y") ?? "-" }}</td>
                <td style="text-align:right;font-weight:500;">${{ number_format($invoice->total, 2) }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($invoice->status ?? "") }}</span></td>
                <td>
                    <a href="{{ route("admin.invoices.show", $invoice) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#999;">{{ __('admin.invoices.no_invoices') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $invoices->withQueryString()->links() }}
    </div>
</div>

@endsection
