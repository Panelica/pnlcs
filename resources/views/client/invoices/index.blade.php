@extends("client.layouts.app")
@section("title", "My Invoices")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">My Invoices</h1>
        <p class="pn-page-subtitle">View and pay your invoices.</p>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr style="{{ in_array(strtolower($inv->status), ["unpaid","overdue"]) ? "background:#fffbeb" : "" }}">
                    <td><a href="{{ route("client.invoices.show", $inv) }}" style="font-weight:600">#{{ $inv->invoice_num ?? $inv->id }}</a></td>
                    <td class="text-muted text-sm">{{ $inv->date?->format("d M Y") ?? "-" }}</td>
                    <td class="text-muted text-sm" style="{{ strtolower($inv->status) === "overdue" ? "color:var(--danger);font-weight:600" : "" }}">{{ $inv->due_date?->format("d M Y") ?? "-" }}</td>
                    <td style="font-weight:700">${{ number_format($inv->total, 2) }}</td>
                    <td><span class="badge badge-{{ strtolower($inv->status) }}">{{ ucfirst($inv->status) }}</span></td>
                    <td>
                        @if(in_array(strtolower($inv->status), ["unpaid", "overdue"]))
                            <a href="{{ route("client.invoices.show", $inv) }}" class="btn btn-accent btn-xs">Pay Now</a>
                        @else
                            <a href="{{ route("client.invoices.show", $inv) }}" class="btn btn-outline btn-xs">View</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128196;</div>
                            <p>No invoices found.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->hasPages())
    <div class="mt-16">{{ $invoices->links() }}</div>
@endif

@endsection
