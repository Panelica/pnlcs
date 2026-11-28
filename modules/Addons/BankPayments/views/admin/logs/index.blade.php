@extends('admin.layouts.app')
@section('title', __('bank-payments::logs.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-list-check"></i> {{ __('bank-payments::logs.title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::logs.subtitle') }}</p>
    </div>
    <a href="{{ route('admin.bank-payments.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::mailboxes.back') }}</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        @if($logs->isEmpty())
            <div style="padding:36px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::logs.no_logs') }}</div>
        @else
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('bank-payments::logs.started') }}</th>
                        <th>{{ __('bank-payments::logs.mailbox') }}</th>
                        <th>{{ __('bank-payments::logs.status') }}</th>
                        <th>{{ __('bank-payments::logs.fetched') }}</th>
                        <th>{{ __('bank-payments::logs.parsed') }}</th>
                        <th>{{ __('bank-payments::logs.new') }}</th>
                        <th>{{ __('bank-payments::logs.duplicates') }}</th>
                        <th>{{ __('bank-payments::logs.errors') }}</th>
                        <th>{{ __('bank-payments::logs.message') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td style="white-space:nowrap;">{{ $log->started_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->mailbox?->name ?? '-' }}</td>
                        <td>{{ $log->status }}</td>
                        <td>{{ $log->fetched }}</td>
                        <td>{{ $log->parsed }}</td>
                        <td>{{ $log->new_transactions }}</td>
                        <td>{{ $log->duplicates }}</td>
                        <td>{{ $log->errors }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $log->message, 140) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<div style="margin-top:14px;">{{ $logs->links() }}</div>

@endsection
