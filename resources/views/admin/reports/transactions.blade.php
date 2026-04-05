@extends("admin.layouts.app")
@section("title", __("admin.transactions_report"))
@section("content")
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Transactions</h1>
    <a href="{{ route("admin.reports.index") }}" class="btn btn-default btn-sm">&larr; Reports</a>
</div>
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('common.table.date') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.description') }}</th>
                <th>Gateway</th>
                <th>Amount In</th>
                <th>Amount Out</th>
                <th>Transaction ID</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
            <tr>
                <td style="font-size:12px;color:#777;">{{ $row->date ? $row->date->format("d M Y") : "-" }}</td>
                <td>
                    @if($row->client)
                        <a href="{{ $row->client ? route("admin.clients.show", $row->client) : "#" }}" style="color:#337ab7;text-decoration:none;">{{ $row->client?->full_name ?? "Deleted Client" }}</a>
                    @else
                        <span style="color:#999;">-</span>
                    @endif
                </td>
                <td style="font-size:12px;">{{ $row->description ?? "-" }}</td>
                <td style="font-size:12px;">{{ $row->gateway ?? "-" }}</td>
                <td style="color:#46a546;font-weight:600;">{{ $row->amount_in > 0 ? "$" . number_format($row->amount_in, 2) : "-" }}</td>
                <td style="color:#c43c35;">{{ $row->amount_out > 0 ? "$" . number_format($row->amount_out, 2) : "-" }}</td>
                <td style="font-family:monospace;font-size:11px;">{{ $row->transaction_id ?? "-" }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">No transactions found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($data->hasPages())
    <div style="padding:12px 16px;border-top:1px solid #f0f0f0;">
        {{ $data->links() }}
    </div>
    @endif
</div>
@endsection
