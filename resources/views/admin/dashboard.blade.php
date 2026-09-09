@extends("admin.layouts.app")
@section("title", __("admin.dashboard.title"))
@section("content")

@if(!empty($setup) && !$setup['complete'])
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--pn-primary,#4f46e5);">
    <div class="card-body" style="padding:16px 20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <strong style="font-size:15px;">{{ __('admin.setup.title') }}</strong>
                <span style="color:var(--pn-muted,#888);font-size:13px;margin-left:8px;">{{ __('admin.setup.progress', ['done' => $setup['done'], 'total' => $setup['total']]) }}</span>
            </div>
            <a href="https://panelica.github.io/pnlcs/getting-started/setup-checklist/" target="_blank" rel="noopener" style="font-size:13px;">{{ __('admin.setup.open_guide') }} &rarr;</a>
        </div>
        <div style="height:6px;background:var(--pn-border,#e5e7eb);border-radius:4px;margin:12px 0;overflow:hidden;">
            <div style="height:100%;width:{{ $setup['total'] ? round($setup['done'] / $setup['total'] * 100) : 0 }}%;background:var(--pn-primary,#4f46e5);"></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px 18px;">
            @foreach($setup['items'] as $item)
            @php
                // The tick is only in the markup when the step is actually done.
                // It used to be printed for every step and hidden with
                // color:transparent, so copied text and screen readers reported
                // five completed steps next to a counter reading "0 of 5".
                $stepState   = $item['done'] ? __('client.status.completed') : __('admin.pending');
                $stepMissing = ! $item['done'] && ! empty($item['missing'])
                    ? implode(', ', $item['missing'])
                    : null;
            @endphp
            <a href="{{ route($item['route']) }}{{ !empty($item['fragment']) ? '#'.$item['fragment'] : '' }}" title="{{ $stepState }}{{ $stepMissing ? ': '.$stepMissing : '' }}" style="display:flex;align-items:center;gap:7px;font-size:13.5px;text-decoration:none;color:{{ $item['done'] ? 'var(--pn-muted,#9ca3af)' : 'var(--pn-text,#374151)' }};">
                <span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;font-size:11px;{{ $item['done'] ? 'background:#16a34a;color:#fff;' : 'border:1.5px solid var(--pn-border,#d1d5db);' }}">{!! $item['done'] ? '&#10003;' : '' !!}</span>
                <span style="{{ $item['done'] ? 'text-decoration:line-through;' : '' }}">
                    {{ __('admin.setup.step_' . $item['key']) }}
                    @if($stepMissing)
                        {{-- Which half is still outstanding, rather than leaving
                             the operator to guess why nothing moved. --}}
                        <span style="color:var(--pn-muted,#9ca3af);">({{ $stepMissing }})</span>
                    @endif
                </span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif

@php $me = auth('admin')->user(); @endphp
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <strong style="font-size:14px;">{{ __('admin.dashboard.quick_actions') }}</strong>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            @if($me && $me->hasPermission('manage_products'))
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.dashboard.new_product') }}</a>
            @endif
            @if($me && $me->hasPermission('manage_servers'))
                <a href="{{ route('admin.config.servers') }}" class="btn btn-default btn-sm">+ {{ __('admin.dashboard.add_server') }}</a>
            @endif
            @if($me && $me->hasPermission('create_clients'))
                <a href="{{ route('admin.clients.create') }}" class="btn btn-default btn-sm">+ {{ __('admin.dashboard.add_client') }}</a>
            @endif
            @if($me && $me->hasPermission('create_invoices'))
                <a href="{{ route('admin.invoices.create') }}" class="btn btn-default btn-sm">+ {{ __('admin.dashboard.new_invoice') }}</a>
            @endif

            {{-- Second half: the screens an operator opens over and over.
                 Creating things was one click away and reaching the screens
                 that manage them was three, through a sidebar tree. --}}
            @php
                $jumps = array_values(array_filter([
                    $me?->hasPermission('manage_gateways') ? ['admin.config.gateways', __('admin.dashboard.payments')] : null,
                    $me?->hasPermission('manage_products') ? ['admin.products.index', __('admin.dashboard.products')] : null,
                    $me?->hasPermission('manage_servers') ? ['admin.config.servers', __('admin.dashboard.servers')] : null,
                    $me?->hasPermission('manage_domains') ? ['admin.config.domain-pricing', __('admin.dashboard.domains')] : null,
                ]));
            @endphp

            @if($jumps)
                {{-- The rule is drawn only when there is something on both
                     sides of it. --}}
                <span aria-hidden="true" style="width:1px;align-self:stretch;background:var(--pn-border,#e5e7eb);margin:0 2px;"></span>
                @foreach($jumps as [$route, $label])
                    <a href="{{ route($route) }}" class="btn btn-default btn-sm">{{ $label }}</a>
                @endforeach
            @endif
        </div>
    </div>

    {{-- What is actually waiting. A count that is zero is left out entirely:
         an operator should be able to tell at a glance that there is nothing
         to do, and a row of zeroes does not say that, it just adds reading. --}}
    @if(!empty($waiting))
    <div class="card-body" style="padding:10px 18px;border-top:1px solid var(--pn-border,#e5e7eb);display:flex;align-items:center;flex-wrap:wrap;gap:8px 14px;">
        <strong style="font-size:13px;color:var(--pn-muted,#6b7280);">{{ __('admin.dashboard.needs_attention') }}</strong>
        @if(!empty($waiting['orders']))
            <a href="{{ route('admin.orders.index') }}?status=pending" style="font-size:13px;text-decoration:none;">
                <strong>{{ $waiting['orders'] }}</strong> {{ __('admin.dashboard.pending_orders') }}
            </a>
        @endif
        @if(!empty($waiting['invoices']))
            <a href="{{ route('admin.invoices.index') }}?status=unpaid" style="font-size:13px;text-decoration:none;">
                <strong>{{ $waiting['invoices'] }}</strong> {{ __('admin.dashboard.unpaid_invoices') }}
            </a>
        @endif
        @if(!empty($waiting['tickets']))
            <a href="{{ route('admin.tickets.index') }}?status=open" style="font-size:13px;text-decoration:none;">
                <strong>{{ $waiting['tickets'] }}</strong> {{ __('admin.dashboard.open_tickets') }}
            </a>
        @endif
    </div>
    @endif
</div>

<div style="display:flex;flex-direction:column;gap:16px;">
@foreach($widgetOutput as $key => $item)
    @php $widget = $item['widget']; $cols = $widget->getColumns(); @endphp
    @if($cols === 4)
        {{-- Full-width widget --}}
        <div class="card">
            <div class="card-body" style="padding:0;">
                {!! $item['html'] !!}
            </div>
        </div>
    @else
        @if($loop->first || ($widgetOutput[array_keys($widgetOutput)[$loop->index - 1] ?? '']['widget'] ?? null)?->getColumns() === 4 || $cols === 2 && !isset($gridOpen))
            @php $gridOpen = true; @endphp
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        @endif
        <div class="card" style="grid-column:span {{ $cols }};">
            <div class="card-header" style="padding:12px 16px;font-size:14px;font-weight:600;display:flex;align-items:center;justify-content:space-between;">
                {{ $widget->getTitle() }}
                <span style="font-size:11px;color:var(--pn-muted);font-weight:400;">{{ $widget->getDescription() }}</span>
            </div>
            <div style="min-height:100px;">
                {!! $item['html'] !!}
            </div>
        </div>
        @if($loop->last)
            </div>
            @php unset($gridOpen); @endphp
        @endif
    @endif
@endforeach
</div>

@endsection
