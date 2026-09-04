@extends('admin.layouts.app')
@section('title', __('admin.affiliates.title'))
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>{{ __('admin.affiliates.title') }}</h1>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('add-affiliate-form').style.display = document.getElementById('add-affiliate-form').style.display === 'none' ? 'block' : 'none';">{{ __('admin.affiliates.add_affiliate') }}</button>
</div>

@if(!($availableClients ?? collect())->isEmpty())
<div class="card" id="add-affiliate-form" style="display:none;margin-bottom:20px;">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.affiliates.store') }}" style="display:flex;gap:8px;align-items:flex-end;">
            @csrf
            <div style="flex:1;">
                <label class="form-label">{{ __('admin.affiliates.select_client') }}</label>
                <select name="client_id" class="form-control" required>
                    <option value="">{{ __('admin.affiliates.select_client') }}</option>
                    @foreach($availableClients as $client)
                    <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }} ({{ $client->email }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.affiliates.add_affiliate') }}</button>
        </form>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ $totalAffiliates }}</div><div style="color:#888;font-size:13px;">{{ __('admin.affiliates.total_affiliates') }}</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ money_fmt($totalEarnings) }}</div><div style="color:#888;font-size:13px;">{{ __('admin.affiliates.total_earnings') }}</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ money_fmt($totalWithdrawn) }}</div><div style="color:#888;font-size:13px;">{{ __('admin.affiliates.total_withdrawn') }}</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ number_format($totalVisitors) }}</div><div style="color:#888;font-size:13px;">{{ __('admin.affiliates.total_visitors') }}</div></div></div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <strong>{{ __('admin.affiliates.all_affiliates') }}</strong>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('common.placeholder.search') }}" value="{{ request('search') }}" style="width:200px;">
            <button class="btn btn-sm btn-default" type="submit">{{ __('common.actions.search') }}</button>
        </form>
    </div>
    <div class="card-body-flush">
        <table class="data-table">
            <thead><tr><th>{{ __('common.table.client') }}</th><th>{{ __('admin.affiliates.visitors') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('common.table.rate') }}</th><th>{{ __('admin.affiliates.balance') }}</th><th>{{ __('admin.affiliates.withdrawn') }}</th><th>{{ __('common.table.actions') }}</th></tr></thead>
            <tbody>
            @forelse($affiliates as $aff)
            <tr>
                <td><a href="{{ route('admin.affiliates.show', $aff) }}">{{ $aff->client?->first_name }} {{ $aff->client?->last_name }}</a></td>
                <td>{{ number_format($aff->visitors) }}</td>
                <td>{{ ucfirst($aff->pay_type) }}</td>
                <td>{{ $aff->pay_type === 'percentage' ? $aff->pay_amount . '%' : '$' . number_format($aff->pay_amount, 2) }}</td>
                <td><strong>{{ money_fmt($aff->balance) }}</strong></td>
                <td>{{ money_fmt($aff->withdrawn) }}</td>
                <td><a href="{{ route('admin.affiliates.show', $aff) }}" class="btn btn-sm btn-default">{{ __('common.actions.view') }}</a></td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#999;">{{ __('admin.affiliates.no_affiliates') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:12px;">{{ $affiliates->links() }}</div>
@endsection
