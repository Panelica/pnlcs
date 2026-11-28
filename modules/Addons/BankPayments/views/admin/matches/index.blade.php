@extends('admin.layouts.app')
@section('title', __('bank-payments::matches.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-link"></i> {{ __('bank-payments::matches.title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::matches.subtitle') }}</p>
    </div>
    <form method="GET" action="{{ route('admin.bank-payments.matches.index') }}" style="display:flex;gap:8px;align-items:flex-end;">
        <div>
            <label class="form-label" style="font-size:12px;">{{ __('bank-payments::matches.filter_status') }}</label>
            <select name="status" class="form-control">
                <option value="">{{ __('bank-payments::matches.all') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ __('bank-payments::matches.status_'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('bank-payments::matches.filter_status') }}</button>
    </form>
</div>

@if($mode === 'test')
<div class="alert" style="margin-bottom:16px;background:#fff8e5;border:1px solid #f1d58b;color:#6b5200;font-size:13px;">
    {{ __('bank-payments::imports.test_mode_no_booking') }}
</div>
@endif

@if($transactions->isEmpty())
<div class="card"><div class="card-body" style="padding:36px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::matches.no_matches') }}</div></div>
@else
    @foreach($transactions as $transaction)
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <strong>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</strong>
                <span style="color:var(--pn-muted);font-size:12px;margin-left:10px;">
                    {{ $transaction->transaction_date?->format('Y-m-d') ?? '-' }}
                    · {{ $transaction->message?->mailbox?->name ?? '-' }}
                </span>
                <div style="font-size:12px;color:var(--pn-muted);margin-top:3px;">
                    {{ $transaction->sender_name ?: '-' }} · {{ $transaction->reference ?: __('bank-payments::transactions.no_reference') }}
                </div>
            </div>
            <div style="text-align:right;">
                <span class="badge {{ $transaction->matching_status === 'auto_ready' ? 'badge-active' : 'badge-pending' }}">
                    {{ __('bank-payments::matches.status_'.$transaction->matching_status) }}
                </span>
                @if($transaction->matching_score !== null)
                    <div style="font-size:12px;color:var(--pn-muted);margin-top:4px;">{{ __('bank-payments::matches.score_points', ['score' => $transaction->matching_score]) }}</div>
                @endif
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            @if($transaction->matches->isEmpty())
                <div style="padding:20px;color:var(--pn-muted);font-size:13px;">{{ $transaction->matching_reason ?: __('bank-payments::matches.no_candidate') }}</div>
            @else
            <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>{{ __('bank-payments::matches.rank') }}</th>
                            <th>{{ __('bank-payments::matches.invoice') }}</th>
                            <th>{{ __('bank-payments::matches.score') }}</th>
                            <th>{{ __('bank-payments::matches.amount') }}</th>
                            <th>{{ __('bank-payments::matches.number_match') }}</th>
                            <th>{{ __('bank-payments::matches.amount_match') }}</th>
                            <th>{{ __('bank-payments::matches.client_match') }}</th>
                            @if($mode !== 'test')
                            <th style="text-align:right;">{{ __('bank-payments::matches.actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($transaction->matches as $match)
                        @php($criteria = $match->criteria ?? [])
                        <tr>
                            <td>{{ $match->rank }}</td>
                            <td>
                                @if($match->invoice)
                                    <a href="{{ route('admin.invoices.show', $match->invoice) }}">{{ $match->invoice->invoice_num ?: $match->invoice->id }}</a>
                                    <div style="font-size:11px;color:var(--pn-muted);">{{ $match->invoice->client?->company_name ?: $match->invoice->client?->full_name }}</div>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $match->score }}</td>
                            <td>{{ number_format((float) $match->amount, 2, ',', ' ') }} {{ data_get($criteria, 'currency', 'PLN') }}</td>
                            <td>{{ !empty($criteria['number_match']) ? 'tak' : '-' }}</td>
                            <td>{{ !empty($criteria['amount_match']) ? 'tak' : '-' }}</td>
                            <td>{{ !empty($criteria['client_match']) ? 'tak' : '-' }}</td>
                            @if($mode !== 'test')
                            <td style="text-align:right;white-space:nowrap;">
                                @if($match->status === 'proposed')
                                <form method="POST" action="{{ route('admin.bank-payments.matches.approve', $match) }}" style="display:inline-flex;gap:4px;align-items:center;">
                                    @csrf
                                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ number_format((float) $match->amount, 2, '.', '') }}" class="form-control" style="width:100px;padding:4px 6px;font-size:12px;">
                                    <button type="submit" class="btn btn-success btn-xs">{{ __('bank-payments::imports.approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.bank-payments.matches.reject', $match) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-xs">{{ __('bank-payments::imports.reject') }}</button>
                                </form>
                                @else
                                    {{ __('bank-payments::matches.status_'.$match->status) }}
                                @endif
                            </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @if($transaction->matching_reason)
                <div style="padding:10px 16px;border-top:1px solid var(--pn-border);font-size:12px;color:var(--pn-muted);">{{ $transaction->matching_reason }}</div>
            @endif
        </div>
    </div>
    @endforeach
@endif

<div style="margin-top:14px;">{{ $transactions->links() }}</div>

@endsection
