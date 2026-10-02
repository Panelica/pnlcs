@extends("client.layouts.app")
@section("title", __("client.invoices.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.invoices.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.invoices.page_subtitle') }}</p>
    </div>
</div>

{{-- Two or more open invoices: tick them and pay them in one go. --}}
<form method="POST" action="{{ route('client.invoices.mass-pay') }}" id="mass-pay-form">@csrf</form>
<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    @if(($payable ?? collect())->count() > 1)<th style="width:28px"><span class="sr-only">{{ __('client.invoices.mass_pay_select') }}</span></th>@endif
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
                    @if(($payable ?? collect())->count() > 1)<td>@if($payable->contains('id', $inv->id))<input type="checkbox" name="invoice_ids[]" value="{{ $inv->id }}" form="mass-pay-form" checked aria-label="{{ __('client.invoices.mass_pay_select') }} #{{ $inv->invoice_num ?? $inv->id }}">@endif</td>@endif
                    <td><a href="{{ route("client.invoices.show", $inv) }}" style="font-weight:600">#{{ $inv->invoice_num ?? $inv->id }}</a></td>
                    <td class="text-muted text-sm">{{ $inv->date?->format(date_fmt()) ?? "-" }}</td>
                    <td class="text-muted text-sm" style="{{ strtolower($inv->status) === "overdue" ? "color:var(--danger);font-weight:600" : "" }}">{{ $inv->due_date?->format(date_fmt()) ?? "-" }}</td>
                    <td style="font-weight:700">{{ money_fmt($inv->total) }}</td>
                    <td><span class="badge badge-{{ strtolower($inv->status) }}">{{ invoice_status_label($inv->status) }}</span></td>
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
                    <td colspan="7">
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
    @if(($payable ?? collect())->count() > 1)
    <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;padding:12px 16px;border-top:1px solid var(--border,#e5e5e5);">
        <span class="text-muted text-sm">{{ __('client.invoices.mass_pay_hint') }}</span>
        <button type="submit" form="mass-pay-form" class="btn btn-accent btn-sm">{{ __('client.invoices.mass_pay') }}</button>
    </div>
    @endif
</div>

@if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->hasPages())
    <div class="mt-16">{{ $invoices->links() }}</div>
@endif

@endsection
