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
    @foreach([
        "" => __('common.form.all'),
        "unpaid" => __('admin.invoices.filter_unpaid'),
        "paid" => __('admin.invoices.filter_paid'),
        "overdue" => __('admin.invoices.filter_overdue'),
        "cancelled" => __('admin.invoices.filter_cancelled'),
        "draft" => __('admin.invoices.filter_draft'),
    ] as $val => $label)
    @php $isActive = (request("status","unpaid") == $val); @endphp
    <a href="{{ route("admin.invoices.index", ["status" => $val]) }}"
       style="display:inline-block;padding:8px 16px;font-size:13px;text-decoration:none;color:{{ $isActive ? "#1a4d80" : "#666" }};font-weight:{{ $isActive ? "700" : "400" }};border-bottom:{{ $isActive ? "3px solid #1a4d80" : "3px solid transparent" }};margin-bottom:-1px;">
        {{ $label }}
    </a>
    @endforeach
</div>

<!-- Table -->
<form id="bulk-form" method="POST" action="{{ route("admin.bulk.invoice-action") }}">
    @csrf
    <input type="hidden" name="action" id="bulk-action" value="">

    <div class="card">
        <div style="padding:10px 16px;border-bottom:1px solid #e5e7eb;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <strong style="font-size:12px;color:#777;margin-right:6px;">{{ __('admin.invoices.bulk_actions') }}:</strong>
            <button type="submit" class="btn btn-success btn-sm" data-action="paid">{{ __('admin.invoices.mark_paid_btn') }}</button>
            <button type="submit" class="btn btn-danger btn-sm" data-action="cancel">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-default btn-sm" data-action="send">{{ __('admin.invoices.send') }}</button>
            <button type="submit" class="btn btn-warning btn-sm" data-action="remind">{{ __('admin.invoices.remind') }}</button>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" id="select-all"></th>
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
                $statusKey = strtolower($invoice->status ?? "");
                $statusLabel = $statusKey === "overdue"
                    ? __('common.status.unpaid') . '/' . __('common.status.overdue')
                    : (__('common.status.' . $statusKey) !== 'common.status.' . $statusKey
                        ? __('common.status.' . $statusKey)
                        : ucfirst($invoice->status ?? ""));
                $badgeStyle = $statusKey === "overdue"
                    ? "background:#fff3cd !important;color:#856404 !important;"
                    : "";
                @endphp
                <tr>
                    <td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="row-checkbox"></td>
                    <td><a href="{{ route("admin.invoices.show", $invoice) }}" style="color:#337ab7;text-decoration:none;font-family:monospace;">#{{ $invoice->id }}</a></td>
                    <td>
                        @if($invoice->client)
                        <a href="{{ route("admin.clients.show", $invoice->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $invoice->client->full_name }}</a>
                        @else N/A @endif
                    </td>
                    <td style="color:#666;">{{ $invoice->date?->format(date_fmt()) ?? "-" }}</td>
                    <td style="color:#666;">{{ $invoice->due_date?->format(date_fmt()) ?? "-" }}</td>
                    <td style="text-align:right;font-weight:500;">{{ money_fmt($invoice->total) }}</td>
                    <td><span class="badge {{ $badgeClass }}" style="{{ $badgeStyle }}">{{ $statusLabel }}</span></td>
                    <td>
                        <a href="{{ route("admin.invoices.show", $invoice) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:32px;color:#999;">{{ __('admin.invoices.no_invoices') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
            {{ $invoices->withQueryString()->links() }}
        </div>
    </div>
</form>

<script>
document.getElementById('select-all').addEventListener('change', function () {
    document.querySelectorAll('.row-checkbox').forEach(function (cb) { cb.checked = this.checked; }, this);
});
document.querySelectorAll('#bulk-form button[data-action]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        var selected = document.querySelectorAll('.row-checkbox:checked');
        if (selected.length === 0) {
            e.preventDefault();
            alert("{{ __('admin.invoices.select_none') }}");
            return;
        }
        document.getElementById('bulk-action').value = this.getAttribute('data-action');
    });
});
</script>

@endsection
