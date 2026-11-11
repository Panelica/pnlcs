@extends('admin.layouts.app')
@section('title', __('admin.server_sync.title', ['name' => $server->name]))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <h1>{{ __('admin.server_sync.title', ['name' => $server->name]) }}</h1>
    <a href="{{ route('admin.config.servers') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.server_sync.back') }}</a>
</div>

<style>
    .ssy-note{font-size:13px;color:#555;margin:0 0 14px;line-height:1.6}
    .ssy-bar{display:flex;gap:14px;flex-wrap:wrap;align-items:center;font-size:12.5px;color:#555;margin-bottom:12px}
    .ssy-bar b{color:#1f2937}
    .ssy-table td{vertical-align:top;font-size:12.5px}
    .ssy-table input,.ssy-table select{font-size:12px;padding:3px 6px;height:auto}
    .ssy-sub{color:#6b7280;font-size:11.5px}
    .ssy-hint{font-size:11px;color:#047857}
    .ssy-table tr[hidden]{display:none}
    .ssy-upload{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12.5px}
</style>

<p class="ssy-note">{{ __('admin.server_sync.intro') }}</p>

@if ($error)
    <div class="alert alert-danger">{{ __('admin.server_sync.list_failed', ['error' => $error]) }}</div>
@endif

@php
    $linkedCount = collect($rows)->filter(fn ($row) => $row['service'] !== null)->count();
    $clientLabels = $clients->mapWithKeys(fn ($client) => [$client->id => $client->display_name.($client->email ? ' · '.$client->email : '')]);
@endphp

<div class="card" style="margin-bottom:14px;">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.servers.sync.price-list', $server) }}" enctype="multipart/form-data" class="ssy-upload">
            @csrf
            <strong>{{ __('admin.server_sync.price_list') }}</strong>
            <input type="file" name="price_list" accept=".csv,text/csv" required>
            <button type="submit" class="btn btn-default btn-xs">{{ __('admin.server_sync.price_list_load') }}</button>
            @if ($priceListCount > 0)
                <span class="ssy-hint">{{ __('admin.server_sync.price_list_active', ['count' => $priceListCount]) }}</span>
            @endif
        </form>
        @if ($priceListCount > 0)
            <form method="POST" action="{{ route('admin.config.servers.sync.price-list', $server) }}" style="margin-top:6px;">
                @csrf
                <input type="hidden" name="clear" value="1">
                <button type="submit" class="btn btn-default btn-xs">{{ __('admin.server_sync.price_list_clear') }}</button>
            </form>
        @endif
        <p class="ssy-sub" style="margin:8px 0 0;">{{ __('admin.server_sync.price_list_help', ['columns' => implode(', ', $priceListColumns), 'cycles' => implode(', ', $cycles)]) }}</p>
    </div>
</div>

<div class="ssy-bar">
    <span>{{ __('admin.server_sync.count_total') }} <b>{{ count($rows) }}</b></span>
    <span>{{ __('admin.server_sync.count_linked') }} <b>{{ $linkedCount }}</b></span>
    <span>{{ __('admin.server_sync.count_unlinked') }} <b>{{ count($rows) - $linkedCount }}</b></span>
    <label style="margin:0;"><input type="checkbox" id="ssy-only-unlinked" checked> {{ __('admin.server_sync.only_unlinked') }}</label>
    <input type="search" id="ssy-filter" class="form-control" style="max-width:240px;font-size:13px;" placeholder="{{ __('admin.server_sync.filter') }}">
</div>

@if ($rows === [])
    <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.server_sync.empty') }}</div></div>
@else
<form method="POST" action="{{ route('admin.config.servers.sync.import', $server) }}" id="ssy-form">
    @csrf
    <datalist id="ssy-clients">
        @foreach ($clientLabels as $clientId => $label)
            <option value="{{ $clientId }}">{{ $label }}</option>
        @endforeach
    </datalist>

    <div class="card">
    <table class="data-table ssy-table">
        <thead><tr>
            <th style="width:28px;">@if ($canImport)<input type="checkbox" id="ssy-all" title="{{ __('admin.server_sync.select_all') }}">@endif</th>
            <th>{{ __('admin.server_sync.col_account') }}</th>
            <th>{{ __('admin.server_sync.col_plan') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th>{{ __('admin.server_sync.col_client') }}</th>
            <th>{{ __('admin.server_sync.col_product') }}</th>
            <th>{{ __('admin.server_sync.col_cycle') }}</th>
            <th>{{ __('admin.server_sync.col_amount') }}</th>
            <th>{{ __('admin.server_sync.col_next_due') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($rows as $row)
            @php($key = 'rows['.$row['id'].']')
            <tr data-ssy-row data-linked="{{ $row['service'] ? 1 : 0 }}" data-search="{{ strtolower($row['domain'].' '.$row['owner'].' '.$row['email']) }}">
                <td>
                    @if (! $row['service'] && $canImport)
                        <input type="checkbox" data-ssy-pick>
                    @endif
                </td>
                <td>
                    <strong>{{ $row['domain'] }}</strong>
                    @if (($row['kind'] ?? null) === 'reseller')
                        <span class="badge badge-pending">{{ __('admin.server_sync.kind_reseller') }}</span>
                    @endif
                    <div class="ssy-sub">{{ $row['owner'] }}@if ($row['email']) · {{ $row['email'] }}@endif</div>
                    @if ($row['created'])
                        <div class="ssy-sub">{{ __('admin.server_sync.created', ['date' => $row['created']]) }}</div>
                    @endif
                </td>
                <td>{{ $row['plan'] ?? '-' }}</td>
                <td><span class="badge {{ $row['status'] === 'active' ? 'badge-active' : 'badge-suspended' }}">{{ __('common.status.'.$row['status']) }}</span></td>
                @if ($row['service'])
                    <td colspan="5">
                        {{ __('admin.server_sync.linked_to') }}
                        <a href="{{ route('admin.clients.show', ['client' => $row['service']->client_id, 'tab' => 'services']) }}">{{ __('admin.server_sync.service_of', ['id' => $row['service']->id, 'client' => $row['service']->client?->display_name]) }}</a>
                    </td>
                @else
                    <td>
                        <input type="text" name="{{ $key }}[client_id]" list="ssy-clients" value="{{ $row['client_id'] }}" class="form-control" style="width:90px;" data-ssy-client disabled>
                        <div class="ssy-sub" data-ssy-client-name>{{ $row['client_id'] ? ($clientLabels[$row['client_id']] ?? '') : '' }}</div>
                    </td>
                    <td>
                        <select name="{{ $key }}[product_id]" class="form-control" disabled>
                            <option value="">-</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected($row['product_id'] === $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="{{ $key }}[billing_cycle]" class="form-control" disabled>
                            @foreach ($cycles as $cycle)
                                <option value="{{ $cycle }}" @selected($row['billing_cycle'] === $cycle)>{{ __('common.billing.'.strtolower(str_replace('-', '_', $cycle))) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0" name="{{ $key }}[amount]" value="{{ $row['amount'] }}" class="form-control" style="width:80px;" disabled></td>
                    <td>
                        <input type="date" name="{{ $key }}[next_due_date]" value="{{ $row['next_due_date'] }}" class="form-control" disabled>
                        @if ($row['from_price_list'])
                            <div class="ssy-hint">{{ __('admin.server_sync.from_price_list') }}</div>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    @if ($canImport)
        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" id="ssy-submit" disabled>{{ __('admin.server_sync.import') }}</button>
            <span class="ssy-sub">{{ __('admin.server_sync.import_help') }}</span>
        </div>
    @else
        <p class="ssy-sub" style="margin-top:12px;">{{ __('admin.server_sync.no_permission') }}</p>
    @endif
</form>

<script>
(() => {
    const rows = [...document.querySelectorAll('[data-ssy-row]')];
    const onlyUnlinked = document.getElementById('ssy-only-unlinked');
    const filter = document.getElementById('ssy-filter');
    const all = document.getElementById('ssy-all');
    const submit = document.getElementById('ssy-submit');
    const labels = new Map([...document.querySelectorAll('#ssy-clients option')].map((o) => [o.value, o.textContent]));
    const maxRows = 100;

    const picked = () => rows.filter((row) => row.querySelector('[data-ssy-pick]')?.checked);
    const refresh = () => {
        const count = picked().length;
        if (submit) {
            submit.disabled = count === 0 || count > maxRows;
            submit.textContent = @js(__('admin.server_sync.import')) + (count ? ' (' + count + ')' : '');
        }
    };
    const show = () => {
        const term = filter.value.trim().toLowerCase();
        rows.forEach((row) => {
            row.hidden = (onlyUnlinked.checked && row.dataset.linked === '1') || (term !== '' && !row.dataset.search.includes(term));
        });
    };

    rows.forEach((row) => {
        const pick = row.querySelector('[data-ssy-pick]');
        pick?.addEventListener('change', () => {
            row.querySelectorAll('input:not([data-ssy-pick]), select').forEach((field) => { field.disabled = !pick.checked; });
            refresh();
        });
        const client = row.querySelector('[data-ssy-client]');
        client?.addEventListener('input', () => {
            row.querySelector('[data-ssy-client-name]').textContent = labels.get(client.value.trim()) || '';
        });
    });

    all?.addEventListener('change', () => {
        let count = 0;
        rows.filter((row) => !row.hidden).forEach((row) => {
            const pick = row.querySelector('[data-ssy-pick]');
            if (pick && pick.checked !== all.checked && (!all.checked || count < maxRows)) {
                pick.checked = all.checked;
                pick.dispatchEvent(new Event('change'));
            }
            if (pick?.checked) { count++; }
        });
    });

    onlyUnlinked.addEventListener('change', show);
    filter.addEventListener('input', show);
    show();
    refresh();
})();
</script>
@endif
@endsection
