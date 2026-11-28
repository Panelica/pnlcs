@extends('admin.layouts.app')
@section('title', $mailbox->exists ? __('bank-payments::mailboxes.edit') : __('bank-payments::mailboxes.add'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">{{ $mailbox->exists ? __('bank-payments::mailboxes.edit') : __('bank-payments::mailboxes.add') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::mailboxes.subtitle') }}</p>
    </div>
    <a href="{{ route('admin.bank-payments.mailboxes.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::mailboxes.back') }}</a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $mailbox->exists ? route('admin.bank-payments.mailboxes.update', $mailbox) : route('admin.bank-payments.mailboxes.store') }}">
            @csrf
            @if($mailbox->exists)
                @method('PUT')
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.name') }} *</label>
                    <input type="text" name="name" class="form-control" required maxlength="100" value="{{ old('name', $mailbox->name) }}">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.bank') }} *</label>
                    <select name="bank" class="form-control" required>
                        @foreach($banks as $bankKey => $bankConfig)
                            <option value="{{ $bankKey }}" @selected(old('bank', $mailbox->bank) === $bankKey)>{{ __($bankConfig['label']) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.host') }} *</label>
                    <input type="text" name="host" class="form-control" required maxlength="255" value="{{ old('host', $mailbox->host) }}">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.port') }} *</label>
                    <input type="number" name="port" class="form-control" required min="1" max="65535" value="{{ old('port', $mailbox->port ?: 993) }}">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.encryption') }}</label>
                    <select name="encryption" class="form-control">
                        @foreach(['ssl', 'tls', 'none'] as $encryption)
                            <option value="{{ $encryption }}" @selected(old('encryption', $mailbox->encryption) === $encryption)>{{ $encryption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.folder') }} *</label>
                    <input type="text" name="folder" class="form-control" required maxlength="255" value="{{ old('folder', $mailbox->folder ?: 'INBOX') }}">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.username') }} *</label>
                    <input type="text" name="username" class="form-control" required maxlength="255" value="{{ old('username', $mailbox->username) }}">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.password') }}{{ $mailbox->exists ? '' : ' *' }}</label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password" {{ $mailbox->exists ? '' : 'required' }}>
                    @if($mailbox->exists)
                        <div style="font-size:12px;color:var(--pn-muted);margin-top:4px;">{{ __('bank-payments::mailboxes.password_keep') }}</div>
                    @endif
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('bank-payments::mailboxes.parser') }}</label>
                    <input type="text" class="form-control" disabled value="{{ $banks[$mailbox->bank]['parser'] ?? '' }}">
                    <div style="font-size:12px;color:var(--pn-muted);margin-top:4px;">{{ __('bank-payments::mailboxes.parser_hint') }}</div>
                </div>
            </div>

            <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:16px;">
                <label class="form-check" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $mailbox->active ?? true))>
                    <span>{{ __('bank-payments::mailboxes.active') }}</span>
                </label>
                <label class="form-check" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="validate_cert" value="0">
                    <input type="checkbox" name="validate_cert" value="1" @checked(old('validate_cert', $mailbox->validate_cert ?? true))>
                    <span>{{ __('bank-payments::mailboxes.validate_cert') }}</span>
                </label>
            </div>
            <p style="font-size:12px;color:var(--pn-muted);margin:6px 0 0;">{{ __('bank-payments::mailboxes.cert_hint') }}</p>

            <div style="display:flex;gap:8px;margin-top:18px;">
                <button type="submit" class="btn btn-primary">{{ __('bank-payments::mailboxes.save') }}</button>
                <a href="{{ route('admin.bank-payments.mailboxes.index') }}" class="btn btn-default">{{ __('bank-payments::mailboxes.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

@endsection
