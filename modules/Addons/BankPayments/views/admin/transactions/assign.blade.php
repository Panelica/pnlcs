@extends('admin.layouts.app')
@section('title', __('bank-payments::matches.assign_title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-link"></i> {{ __('bank-payments::matches.assign_title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">
            {{ $transaction->transaction_date?->format('Y-m-d') ?? '-' }}
            · {{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}
            · {{ $transaction->sender_name ?: '-' }}
        </p>
    </div>
    <a href="{{ route('admin.bank-payments.matches.index') }}" class="btn btn-default btn-sm">&larr; {{ __('bank-payments::matches.title') }}</a>
</div>

@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.bank-payments.transactions.assign', $transaction) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
                <label class="form-label">{{ __('bank-payments::matches.assign_search') }}</label>
                <input type="text" name="q" value="{{ $query }}" class="form-control" placeholder="{{ __('bank-payments::matches.assign_search_placeholder') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('bank-payments::matches.assign_search_button') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('bank-payments::matches.open_invoice') }}</strong></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        @if($invoices->isEmpty())
            <div style="padding:32px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::matches.assign_no_results') }}</div>
        @else
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>{{ __('bank-payments::matches.invoice') }}</th>
                    <th>{{ __('bank-payments::matches.client') }}</th>
                    <th>{{ __('bank-payments::matches.invoice_balance') }}</th>
                    <th style="text-align:right;">{{ __('bank-payments::matches.amount') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($invoices as $invoice)
                @php($balance = round((float) $invoice->amountDue(), 2))
                <tr>
                    <td>
                        <a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->invoice_num ?: $invoice->id }}</a>
                        <div style="font-size:11px;color:var(--pn-muted);">{{ $invoice->date?->format('Y-m-d') }}</div>
                    </td>
                    <td>{{ $invoice->client?->company_name ?: $invoice->client?->full_name }}</td>
                    <td>{{ number_format($balance, 2, ',', ' ') }} PLN</td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('admin.bank-payments.transactions.assign', $transaction) }}" style="display:inline-flex;gap:4px;align-items:center;">
                            @csrf
                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format($balance, 2, '.', '') }}"
                                   value="{{ number_format(min((float) $transaction->amount, $balance), 2, '.', '') }}"
                                   class="form-control" style="width:110px;padding:4px 6px;font-size:12px;">
                            <button type="submit" class="btn btn-success btn-xs">{{ __('bank-payments::matches.assign') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@endsection
