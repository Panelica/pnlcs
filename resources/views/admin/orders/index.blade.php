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
<form id="order-bulk-form" method="POST" action="{{ route('admin.orders.bulk') }}">
@csrf
<input type="hidden" name="action" id="order-bulk-action" value="">
<div class="card">
    @if(auth('admin')->user()?->hasPermission('manage_orders'))
    <div style="padding:10px 16px;border-bottom:1px solid #e5e7eb;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <strong style="font-size:12px;color:#777;margin-right:6px;">{{ __('admin.invoices.bulk_actions') }}:</strong>
        <button type="submit" class="btn btn-success btn-sm" data-action="accept">{{ __('admin.orders.accept_order') }}</button>
        <button type="submit" class="btn btn-danger btn-sm" data-action="cancel">{{ __('admin.orders.cancel_order') }}</button>
    </div>
    @endif
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:30px;"><input type="checkbox" id="order-select-all" aria-label="{{ __('admin.bulk.select_all') }}"></th>
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
                <td><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" class="order-row-checkbox" aria-label="#{{ $order->order_num }}"></td>
                <td><a href="{{ route("admin.orders.show", $order) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $order->order_num }}</a></td>
                <td>
                    @if($order->client)
                    <a href="{{ route("admin.clients.show", $order->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $order->client?->full_name ?? "Deleted Client" }}</a>
                    @else N/A @endif
                </td>
                <td style="color:#666;">{{ $order->date?->format(date_fmt()) ?? "-" }}</td>
                <td style="text-align:right;font-weight:500;">{{ money_fmt($order->amount) }}</td>
                <td style="color:#666;">{{ $order->payment_method ? payment_method_label((string) $order->payment_method) : "-" }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($order->status ?? "") }}</span></td>
                <td>
                    <a href="{{ route("admin.orders.show", $order) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;padding:32px;color:#999;">{{ __('admin.orders.no_orders') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>
</form>

<script>
document.getElementById('order-select-all').addEventListener('change', function () {
    document.querySelectorAll('.order-row-checkbox').forEach(function (cb) { cb.checked = this.checked; }, this);
});
document.querySelectorAll('#order-bulk-form button[data-action]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (document.querySelectorAll('.order-row-checkbox:checked').length === 0) {
            e.preventDefault();
            alert(@json(__('admin.orders.select_none')));
            return;
        }
        if (this.getAttribute('data-action') === 'cancel' && !confirm(@json(__('admin.orders.confirm_cancel')))) {
            e.preventDefault();
            return;
        }
        document.getElementById('order-bulk-action').value = this.getAttribute('data-action');
    });
});
</script>

@endsection
