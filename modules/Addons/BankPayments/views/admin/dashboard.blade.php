@extends('admin.layouts.app')
@section('title', __('bank-payments::dashboard.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-university"></i> {{ __('bank-payments::dashboard.title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::dashboard.subtitle') }}</p>
    </div>
    <form method="POST" action="{{ route('admin.bank-payments.sync') }}" style="margin:0;">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-rotate"></i> {{ __('bank-payments::dashboard.sync_all') }}
        </button>
    </form>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

<div class="alert" style="margin-bottom:16px;background:#fff8e5;border:1px solid #f1d58b;color:#6b5200;font-size:13px;">
    {{ __('bank-payments::dashboard.test_mode_notice') }}
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px;">
    @foreach([
        ['label' => __('bank-payments::dashboard.mailboxes'), 'value' => $mailboxCount],
        ['label' => __('bank-payments::dashboard.active_mailboxes'), 'value' => $activeMailboxCount],
        ['label' => __('bank-payments::dashboard.messages'), 'value' => $messageCount],
        ['label' => __('bank-payments::dashboard.transactions'), 'value' => $transactionCount],
        ['label' => __('bank-payments::dashboard.pending_matches'), 'value' => $pendingMatchCount],
    ] as $card)
    <div class="card" style="margin:0;">
        <div class="card-body" style="padding:16px;">
            <div style="font-size:12px;color:var(--pn-muted);margin-bottom:6px;">{{ $card['label'] }}</div>
            <div style="font-size:26px;font-weight:700;">{{ number_format($card['value'], 0, ',', ' ') }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 16px;">
        <div style="font-size:13px;">
            <strong>{{ __('bank-payments::dashboard.mode') }}:</strong> {{ __('bank-payments::settings.mode_test') }}
            @if($lastSync)
                <span style="color:var(--pn-muted);margin-left:14px;">
                    {{ __('bank-payments::dashboard.last_sync') }}:
                    {{ $lastSync->started_at?->format('Y-m-d H:i') }}
                    — {{ $lastSync->status }}
                </span>
            @endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.bank-payments.mailboxes.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::dashboard.manage_mailboxes') }}</a>
            <a href="{{ route('admin.bank-payments.transactions.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::dashboard.view_transactions') }}</a>
            <a href="{{ route('admin.bank-payments.matches.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::matches.title') }}</a>
            <a href="{{ route('admin.bank-payments.logs.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::dashboard.view_logs') }}</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span style="font-weight:600;">{{ __('bank-payments::dashboard.recent_messages') }}</span></div>
    <div class="card-body" style="padding:0;">
        @if($recentMessages->isEmpty())
            <div style="padding:28px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::dashboard.no_messages') }}</div>
        @else
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('bank-payments::transactions.date') }}</th>
                        <th>{{ __('bank-payments::transactions.mailbox') }}</th>
                        <th>{{ __('bank-payments::transactions.subject') }}</th>
                        <th>{{ __('bank-payments::transactions.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentMessages as $message)
                    <tr>
                        <td>{{ $message->received_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>{{ $message->mailbox?->name ?? '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $message->subject, 90) ?: '-' }}</td>
                        <td>{{ $message->status }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
