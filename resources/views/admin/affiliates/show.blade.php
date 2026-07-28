@extends('admin.layouts.app')
@section('title', __('admin.affiliates.title_show') . ' - ' . ($affiliate->client?->first_name ?? '') . ' ' . ($affiliate->client?->last_name ?? ''))
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Affiliate: {{ $affiliate->client?->first_name }} {{ $affiliate->client?->last_name }}</h1>
    <a href="{{ route('admin.affiliates.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.affiliates.back') }}</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.affiliates.affiliate_details') }}</strong></div>
        <div class="card-body">
            <table class="table mb-0">
                <tr><td style="width:140px;"><strong>{{ __('admin.affiliates.client_label') }}</strong></td><td>{{ $affiliate->client?->first_name }} {{ $affiliate->client?->last_name }} ({{ $affiliate->client?->email }})</td></tr>
                <tr><td><strong>{{ __('admin.affiliates.visitors_label') }}</strong></td><td>{{ number_format($affiliate->visitors) }}</td></tr>
                <tr><td><strong>{{ __('admin.affiliates.balance_label') }}</strong></td><td><strong>${{ number_format($affiliate->balance, 2) }}</strong></td></tr>
                <tr><td><strong>{{ __('admin.affiliates.withdrawn_label') }}</strong></td><td>${{ number_format($affiliate->withdrawn, 2) }}</td></tr>
                <tr><td><strong>{{ __('admin.affiliates.referral_link') }}</strong></td><td><code>{{ url('/') }}?ref={{ $affiliate->id }}</code></td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>{{ __('admin.affiliates.settings') }}</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}">
                @csrf @method('PUT')
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">{{ __('admin.affiliates.commission_type') }}</label>
                    <select name="pay_type" class="form-control">
                        <option value="percentage" {{ $affiliate->pay_type === 'percentage' ? 'selected' : '' }}>{{ __('admin.affiliates.percentage') }}</option>
                        <option value="flat" {{ $affiliate->pay_type === 'flat' ? 'selected' : '' }}>{{ __('admin.affiliates.flat_amount') }}</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">{{ __('admin.affiliates.commission_amount') }}</label>
                    <input type="number" name="pay_amount" class="form-control" step="0.01" value="{{ $affiliate->pay_amount }}">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label><input type="checkbox" name="onetime" value="1" {{ $affiliate->onetime ? 'checked' : '' }}> {{ __('admin.affiliates.onetime_commission') }}</label>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">{{ __('admin.affiliates.save_settings') }}</button>
            </form>

            @if($affiliate->balance > 0)
            <hr>
            <form method="POST" action="{{ route('admin.affiliates.payout', $affiliate) }}" style="display:flex;gap:8px;align-items:flex-end;">
                @csrf
                <div>
                    <label class="form-label">{{ __('admin.affiliates.payout_amount') }}</label>
                    <input type="number" name="amount" step="0.01" max="{{ $affiliate->balance }}" class="form-control" style="width:150px;" value="{{ $affiliate->balance }}">
                </div>
                <button class="btn btn-success btn-sm" type="submit">{{ __('admin.affiliates.process_payout') }}</button>
            </form>
            @endif
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.affiliates.referral_history') }}</strong></div>
    <div class="card-body-flush">
        <table class="table table-striped mb-0">
            <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('common.table.description') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('admin.affiliates.gateway') }}</th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td>{{ $tx->date?->format(datetime_fmt()) ?? 'N/A' }}</td>
                <td>{{ $tx->description }}</td>
                <td>${{ number_format(abs($tx->amount), 2) }}</td>
                <td>{{ $tx->gateway }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#999;">{{ __('admin.affiliates.no_referrals') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="margin-top:12px;">{{ $transactions->links() }}</div>
@endsection
