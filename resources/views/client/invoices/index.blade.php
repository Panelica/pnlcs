@extends("client.layouts.app")
@section("title", __("client.invoices.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.invoices.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.invoices.page_subtitle') }}</p>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.invoice_num') }}</th>
                    <th>{{ __('common.table.date') }}</th>
                    <th>{{ __('common.table.due_date') }}</th>
                    <th>{{ __('common.table.total') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th>{{ __('common.table.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr style="{{ in_array(strtolower($inv->status), ["unpaid","overdue"]) ? "background:#fffbeb" : "" }}">
                    <td><a href="{{ route("client.invoices.show", $inv) }}" style="font-weight:600">#{{ $inv->invoice_num ?? $inv->id }}</a></td>
                    <td class="text-muted text-sm">{{ $inv->date?->format(date_fmt()) ?? "-" }}</td>
                    <td class="text-muted text-sm" style="{{ strtolower($inv->status) === "overdue" ? "color:var(--danger);font-weight:600" : "" }}">{{ $inv->due_date?->format(date_fmt()) ?? "-" }}</td>
                    <td style="font-weight:700">${{ number_format($inv->total, 2) }}</td>
                    <td><span class="badge badge-{{ strtolower($inv->status) }}">{{ __('client.status.' . strtolower($inv->status)) }}</span></td>
                    <td>
                        @if(in_array(strtolower($inv->status), ["unpaid", "overdue"]))
                            <a href="{{ route("client.invoices.show", $inv) }}" class="btn btn-accent btn-xs">{{ __('common.actions.pay_now') }}</a>
                        @else
                            <a href="{{ route("client.invoices.show", $inv) }}" class="btn btn-outline btn-xs">{{ __('common.actions.view') }}</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128196;</div>
                            <p>{{ __('admin.invoices.no_invoices') }}</p>
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
