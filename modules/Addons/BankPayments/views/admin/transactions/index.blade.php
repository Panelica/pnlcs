@extends('admin.layouts.app')
@section('title', __('bank-payments::transactions.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-receipt"></i> {{ __('bank-payments::transactions.title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::transactions.subtitle') }}</p>
    </div>
    <a href="{{ route('admin.bank-payments.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::mailboxes.back') }}</a>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 16px;">
        <form method="GET" action="{{ route('admin.bank-payments.transactions.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label class="form-label" style="font-size:12px;">{{ __('bank-payments::transactions.mailbox') }}</label>
                <select name="mailbox" class="form-control">
                    <option value="">{{ __('bank-payments::transactions.all') }}</option>
                    @foreach($mailboxes as $mailbox)
                        <option value="{{ $mailbox->id }}" @selected((string) ($filters['mailbox'] ?? '') === (string) $mailbox->id)>{{ $mailbox->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:12px;">{{ __('bank-payments::transactions.currency') }}</label>
                <input type="text" name="currency" class="form-control" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="PLN">
            </div>
            <div>
                <label class="form-label" style="font-size:12px;">{{ __('bank-payments::matches.matching_status') }}</label>
                <select name="matching" class="form-control">
                    <option value="">{{ __('bank-payments::transactions.all') }}</option>
                    @foreach(['pending','review','ambiguous','auto_ready','unmatched','manual_currency','error'] as $matchingStatus)
                        <option value="{{ $matchingStatus }}" @selected(($filters['matching'] ?? '') === $matchingStatus)>{{ __('bank-payments::matches.status_'.$matchingStatus) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:12px;">{{ __('bank-payments::transactions.reference') }} / {{ __('bank-payments::transactions.sender') }}</label>
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('bank-payments::transactions.filter') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        @if($transactions->isEmpty())
            <div style="padding:36px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::transactions.no_transactions') }}</div>
        @else
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('bank-payments::transactions.date') }}</th>
                        <th>{{ __('bank-payments::transactions.amount') }}</th>
                        <th>{{ __('bank-payments::transactions.sender') }}</th>
                        <th>{{ __('bank-payments::transactions.reference') }}</th>
                        <th>{{ __('bank-payments::transactions.mailbox') }}</th>
                        <th>{{ __('bank-payments::transactions.status') }}</th>
                        <th>{{ __('bank-payments::matches.matching_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->transaction_date?->format('Y-m-d') ?? '-' }}</td>
                        <td style="white-space:nowrap;font-weight:600;">
                            {{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}
                        </td>
                        <td>{{ $transaction->sender_name ?: '-' }}</td>
                        <td>
                            <a href="{{ route('admin.bank-payments.transactions.show', $transaction) }}">
                                {{ \Illuminate\Support\Str::limit((string) ($transaction->reference ?: __('bank-payments::transactions.no_reference')), 90) }}
                            </a>
                        </td>
                        <td>{{ $transaction->message?->mailbox?->name ?? '-' }}</td>
                        <td>{{ $transaction->message?->status ?? '-' }}</td>
                        <td>
                            {{ __('bank-payments::matches.status_'.$transaction->matching_status) }}
                            @if($transaction->matching_score !== null)
                                <div style="font-size:11px;color:var(--pn-muted);">{{ $transaction->scorePercent() }}%</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<div style="margin-top:14px;">{{ $transactions->links() }}</div>

@endsection
