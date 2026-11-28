@extends('admin.layouts.app')
@section('title', __('bank-payments::mailboxes.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-envelope"></i> {{ __('bank-payments::mailboxes.title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::mailboxes.subtitle') }}</p>
    </div>
    <a href="{{ route('admin.bank-payments.mailboxes.create') }}" class="btn btn-primary btn-sm">+ {{ __('bank-payments::mailboxes.add') }}</a>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-body" style="padding:0;">
        @if($mailboxes->isEmpty())
            <div style="padding:36px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::mailboxes.no_mailboxes') }}</div>
        @else
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>{{ __('bank-payments::mailboxes.name') }}</th>
                    <th>{{ __('bank-payments::mailboxes.host') }}</th>
                    <th>{{ __('bank-payments::mailboxes.status') }}</th>
                    <th>{{ __('bank-payments::mailboxes.messages_count') }}</th>
                    <th>{{ __('bank-payments::mailboxes.last_sync') }}</th>
                    <th style="text-align:right;">{{ __('bank-payments::mailboxes.actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($mailboxes as $mailbox)
                <tr>
                    <td>
                        <strong>{{ $mailbox->name }}</strong>
                        <div style="font-size:11px;color:var(--pn-muted);">{{ __($banks[$mailbox->bank]['label'] ?? '') }}</div>
                    </td>
                    <td>
                        {{ $mailbox->host }}:{{ $mailbox->port }}
                        <div style="font-size:11px;color:var(--pn-muted);">{{ $mailbox->encryption }} / {{ $mailbox->folder }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $mailbox->active ? 'badge-active' : 'badge-pending' }}">
                            {{ $mailbox->active ? __('bank-payments::mailboxes.active_label') : __('bank-payments::mailboxes.inactive_label') }}
                        </span>
                    </td>
                    <td>{{ $mailbox->messages_count }}</td>
                    <td>
                        {{ $mailbox->last_sync_at?->format('Y-m-d H:i') ?? __('bank-payments::mailboxes.never') }}
                        @if($mailbox->last_error)
                            <div style="font-size:11px;color:#b02a37;max-width:320px;">{{ \Illuminate\Support\Str::limit($mailbox->last_error, 120) }}</div>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <form method="POST" action="{{ route('admin.bank-payments.mailboxes.test', $mailbox) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-default btn-xs">{{ __('bank-payments::mailboxes.test') }}</button>
                        </form>
                        <a href="{{ route('admin.bank-payments.mailboxes.edit', $mailbox) }}" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</a>
                        <form method="POST" action="{{ route('admin.bank-payments.mailboxes.destroy', $mailbox) }}" style="display:inline;" onsubmit="return pnConfirm(event, @js(__('bank-payments::mailboxes.confirm_delete')));">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
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
