@extends("client.layouts.app")
@section("title", __("client.services.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.services.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.services.page_subtitle') }}</p>
    </div>
    <a href="{{ route("client.store") }}" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('client.services.order_new') }}
    </a>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.product') }}</th>
                    <th>{{ __('common.table.domain') }}</th>
                    <th>{{ __('common.table.billing_cycle') }}</th>
                    <th>{{ __('common.table.amount') }}</th>
                    <th>{{ __('common.table.next_due') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $s)
                <tr>
                    <td>
                        <a href="{{ route("client.services.show", $s) }}" style="font-weight:600">{{ $s->product?->name ?? "N/A" }}</a>
                        @include('client.services.partials.tool-links', ['svc' => $s])
                    </td>
                    <td class="text-muted">{{ $s->domain ?? "-" }}</td>
                    <td class="text-muted" style="text-transform:capitalize">{{ $s->billing_cycle ?? "-" }}</td>
                    <td style="font-weight:600">{{ money_fmt($s->amount) }}</td>
                    <td class="text-muted text-sm">{{ $s->next_due_date?->format(date_fmt()) ?? "-" }}</td>
                    <td><span class="badge badge-{{ strtolower($s->status) }}">{{ __('client.status.' . strtolower($s->status)) }}</span></td>
                    <td><a href="{{ route("client.services.show", $s) }}" class="btn btn-outline btn-xs">{{ __('common.actions.view') }}</a></td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128722;</div>
                            <p>{{ __('client.services.no_services') }}</p>
                            <a href="{{ route("client.store") }}" class="btn btn-primary">{{ __('client.services.order_first') }}</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($services instanceof \Illuminate\Pagination\LengthAwarePaginator && $services->hasPages())
    <div class="mt-16">{{ $services->links() }}</div>
@endif

@endsection
