@extends("admin.layouts.app")
@section("title", __("admin.payment_notifications.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.payment_notifications.title') }}</h1>
</div>

<!-- Status Filter Tabs -->
<div style="margin-bottom:16px;border-bottom:1px solid #ddd;display:flex;gap:0;flex-wrap:wrap;">
    @foreach(["pending" => __('admin.payment_notifications.filter_pending'), "approved" => __('admin.payment_notifications.filter_approved'), "rejected" => __('admin.payment_notifications.filter_rejected'), "all" => __('admin.payment_notifications.filter_all')] as $val => $label)
    @php $isActive = ($status == $val); @endphp
    <a href="{{ route("admin.payment-notifications.index", ["status" => $val]) }}"
       style="display:inline-block;padding:8px 16px;font-size:13px;text-decoration:none;color:{{ $isActive ? "#1a4d80" : "#666" }};font-weight:{{ $isActive ? "700" : "400" }};border-bottom:{{ $isActive ? "3px solid #1a4d80" : "3px solid transparent" }};margin-bottom:-1px;">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('common.table.invoice_num') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('admin.payment_notifications.sender') }}</th>
                <th style="text-align:right;">{{ __('common.table.amount') }}</th>
                <th>{{ __('admin.payment_notifications.transfer_date') }}</th>
                <th>{{ __('admin.payment_notifications.receipt') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($notifications as $pn)
            <tr>
                <td>{{ $pn->id }}</td>
                <td>
                    @if($pn->invoice)
                    <a href="{{ route('admin.invoices.show', $pn->invoice) }}">#{{ $pn->invoice->invoice_num ?? $pn->invoice->id }}</a>
                    @else
                    —
                    @endif
                </td>
                <td>
                    @if($pn->client)
                    <a href="{{ route('admin.clients.show', $pn->client) }}">{{ $pn->client->first_name }} {{ $pn->client->last_name }}</a>
                    @else
                    —
                    @endif
                </td>
                <td>
                    {{ $pn->sender_name }}
                    @if($pn->bank_name)<br><small style="color:#888;">{{ $pn->bank_name }}</small>@endif
                    @if($pn->reference)<br><small style="color:#888;">{{ __('admin.payment_notifications.reference') }}: {{ $pn->reference }}</small>@endif
                </td>
                <td style="text-align:right;"><strong>${{ number_format((float) $pn->amount, 2) }}</strong></td>
                <td>{{ $pn->transfer_date?->format('Y-m-d') }}</td>
                <td>
                    @if($pn->receipt_path)
                    <a href="{{ route('admin.payment-notifications.receipt', $pn) }}" target="_blank">{{ __('admin.payment_notifications.view_receipt') }}</a>
                    @else
                    —
                    @endif
                </td>
                <td>
                    @php
                        $badgeColor = match($pn->status) {
                            'approved' => '#28a745',
                            'rejected' => '#dc3545',
                            default    => '#f0ad4e',
                        };
                    @endphp
                    <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;color:#fff;background:{{ $badgeColor }};">
                        {{ __('admin.payment_notifications.status_' . $pn->status) }}
                    </span>
                    @if($pn->reviewed_at)
                    <br><small style="color:#888;">{{ $pn->reviewed_at->format('Y-m-d H:i') }} — {{ $pn->admin?->username ?? '—' }}</small>
                    @endif
                    @if($pn->admin_note)
                    <br><small style="color:#888;">{{ $pn->admin_note }}</small>
                    @endif
                </td>
                <td>
                    @if($pn->status === 'pending')
                    <form method="POST" action="{{ route('admin.payment-notifications.approve', $pn) }}" style="display:inline;"
                          onsubmit="return confirm('{{ __('admin.payment_notifications.approve_confirm') }}');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">{{ __('admin.payment_notifications.approve') }}</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('reject-form-{{ $pn->id }}').style.display='table-row';">
                        {{ __('admin.payment_notifications.reject') }}
                    </button>
                    @else
                    —
                    @endif
                </td>
            </tr>
            @if($pn->status === 'pending')
            <tr id="reject-form-{{ $pn->id }}" style="display:none;background:#fff5f5;">
                <td colspan="9">
                    <form method="POST" action="{{ route('admin.payment-notifications.reject', $pn) }}" style="display:flex;gap:8px;align-items:center;">
                        @csrf
                        <input type="text" name="admin_note" class="form-control" style="flex:1;" required
                               placeholder="{{ __('admin.payment_notifications.reject_reason_placeholder') }}">
                        <button type="submit" class="btn btn-sm btn-danger">{{ __('admin.payment_notifications.reject_confirm') }}</button>
                    </form>
                </td>
            </tr>
            @endif
            @empty
            <tr><td colspan="9" style="text-align:center;color:#888;padding:24px;">{{ __('admin.payment_notifications.none_found') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $notifications->links() }}</div>

@endsection
