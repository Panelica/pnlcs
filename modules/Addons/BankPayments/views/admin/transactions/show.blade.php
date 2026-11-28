@extends('admin.layouts.app')
@section('title', __('bank-payments::transactions.details'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">{{ __('bank-payments::transactions.details') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">
            {{ $transaction->transaction_date?->format('Y-m-d') ?? '-' }}
            · {{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}
        </p>
    </div>
    <a href="{{ route('admin.bank-payments.transactions.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::transactions.back') }}</a>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <table class="data-table" style="width:100%;">
            <tbody>
                <tr><th style="width:220px;">{{ __('bank-payments::transactions.amount') }}</th><td>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.date') }}</th><td>{{ $transaction->transaction_date?->format('Y-m-d') ?? '-' }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.operation') }}</th><td>{{ $transaction->operation_type ?: '-' }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.sender') }}</th><td>{{ $transaction->sender_name ?: '-' }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.account') }}</th><td>{{ $transaction->sender_account ?: '-' }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.recipient_account') }}</th><td>{{ $transaction->recipient_account ?: '-' }}</td></tr>
                <tr><th>{{ __('bank-payments::transactions.reference') }}</th><td>{{ $transaction->reference ?: '-' }}</td></tr>
                <tr>
                    <th>{{ __('bank-payments::matches.matching_status') }}</th>
                    <td>
                        {{ __('bank-payments::matches.status_'.$transaction->matching_status) }}
                        @if($transaction->matching_score !== null)
                            · {{ __('bank-payments::matches.score_points', ['score' => $transaction->matching_score]) }}
                        @endif
                        @if($transaction->matching_reason)
                            <div style="font-size:12px;color:var(--pn-muted);">{{ $transaction->matching_reason }}</div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if($transaction->import)
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><span style="font-weight:600;">{{ __('bank-payments::imports.imported') }}</span></div>
    <div class="card-body">
        <div style="font-size:13px;line-height:1.7;">
            <div><strong>{{ __('bank-payments::imports.status') }}:</strong> {{ __('bank-payments::imports.status_'.$transaction->import->status) }}</div>
            <div><strong>{{ __('bank-payments::imports.reference') }}:</strong> {{ $transaction->import->payment_reference }}</div>
            <div><strong>{{ __('bank-payments::matches.amount') }}:</strong> {{ number_format((float) $transaction->import->amount, 2, ',', ' ') }}</div>
            <div><strong>{{ __('bank-payments::imports.date') }}:</strong> {{ $transaction->import->imported_at?->format('Y-m-d H:i') ?? '-' }}</div>
        </div>
    </div>
</div>
@endif

@if($transaction->matches->isNotEmpty())
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><span style="font-weight:600;">{{ __('bank-payments::matches.title') }}</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>{{ __('bank-payments::matches.rank') }}</th>
                    <th>{{ __('bank-payments::matches.invoice') }}</th>
                    <th>{{ __('bank-payments::matches.score') }}</th>
                    <th>{{ __('bank-payments::matches.amount') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($transaction->matches as $match)
                <tr>
                    <td>{{ $match->rank }}</td>
                    <td>
                        @if($match->invoice)
                            <a href="{{ route('admin.invoices.show', $match->invoice) }}">{{ $match->invoice->invoice_num ?: $match->invoice->id }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $match->score }}</td>
                    <td>{{ number_format((float) $match->amount, 2, ',', ' ') }} {{ data_get($match->criteria, 'currency', 'PLN') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><span style="font-weight:600;">{{ __('bank-payments::transactions.message') }}</span></div>
    <div class="card-body">
        @if($transaction->message)
            <div style="font-size:13px;line-height:1.7;margin-bottom:14px;">
                <div><strong>{{ __('bank-payments::transactions.subject') }}:</strong> {{ $transaction->message->subject ?: '-' }}</div>
                <div><strong>{{ __('bank-payments::transactions.from') }}:</strong> {{ $transaction->message->from_name ?: $transaction->message->from_email ?: '-' }}</div>
                <div><strong>{{ __('bank-payments::transactions.mailbox') }}:</strong> {{ $transaction->message->mailbox?->name ?? '-' }}</div>
                <div><strong>{{ __('bank-payments::transactions.uid') }}:</strong> {{ $transaction->message->uid }} / {{ $transaction->message->uidvalidity }}</div>
                <div><strong>{{ __('bank-payments::transactions.status') }}:</strong> {{ $transaction->message->status }}</div>
            </div>
            <div style="border:1px solid var(--pn-border);border-radius:4px;padding:14px;background:#fafafa;max-height:460px;overflow:auto;white-space:pre-wrap;font-size:13px;line-height:1.55;">{{ $transaction->message->body_text }}</div>
        @else
            <p style="color:var(--pn-muted);margin:0;">-</p>
        @endif
    </div>
</div>

@endsection
