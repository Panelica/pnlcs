@extends('admin.layouts.app')
@section('title', __('admin.clients.credit_title').' - '.$client->full_name)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h1>{{ __('admin.clients.credit_title') }}</h1>
        <div style="font-size:13px;color:#777;margin-top:3px;"><a href="{{ route('admin.clients.show', $client) }}">{{ $client->full_name }}</a> &mdash; {{ __('admin.clients.credit_balance') }}: <strong style="color:#3c763d;">{{ money_fmt($client->credit) }}</strong></div>
    </div>
    <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-default btn-sm">&larr; {{ $client->full_name }}</a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card" style="margin-bottom:15px;max-width:760px;">
    <form method="POST" action="{{ route('admin.clients.credit.store', $client) }}">
        @csrf
        <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="cr-type">{{ __('common.table.type') }}</label>
                <select id="cr-type" name="type" class="form-control">
                    <option value="add" @selected(old('type') !== 'remove')>{{ __('admin.clients.credit_add') }}</option>
                    <option value="remove" @selected(old('type') === 'remove')>{{ __('admin.clients.credit_remove') }}</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="cr-amount">{{ __('common.table.amount') }}</label>
                <input type="number" id="cr-amount" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="form-control">
            </div>
            <div class="form-group" style="margin:0;grid-column:span 2;">
                <label class="form-label" for="cr-desc">{{ __('common.table.description') }}</label>
                <input type="text" id="cr-desc" name="description" maxlength="255" value="{{ old('description') }}" required class="form-control">
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.clients.credit_history') }}</strong></div>
    @if($history->isEmpty())
    <div class="card-body" style="color:#999;font-size:13px;">{{ __('admin.clients.credit_none') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('common.table.description') }}</th><th style="text-align:right;">{{ __('common.table.amount') }}</th></tr></thead>
        <tbody>
        @foreach($history as $row)
        <tr>
            <td style="white-space:nowrap;">{{ $row->date?->format(date_fmt()) }}</td>
            <td>{{ $row->description }}</td>
            <td style="text-align:right;font-weight:600;color:{{ (float) $row->amount < 0 ? '#a94442' : '#3c763d' }};">{{ (float) $row->amount < 0 ? '−' : '+' }}{{ money_fmt(abs((float) $row->amount)) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $history->links() }}</div>
    @endif
</div>
@endsection
