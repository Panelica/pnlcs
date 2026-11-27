<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
    <a href="{{ route('admin.bank-payments.index') }}" class="btn btn-primary btn-sm">{{ __('bank-payments::dashboard.title') }}</a>
    @if(auth('admin')->user()?->role?->hasPermission('manage_settings'))
        <a href="{{ route('admin.bank-payments.mailboxes.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::mailboxes.title') }}</a>
    @endif
    <a href="{{ route('admin.bank-payments.transactions.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::transactions.title') }}</a>
    <a href="{{ route('admin.bank-payments.matches.index') }}" class="btn btn-default btn-sm">{{ __('bank-payments::matches.title') }}</a>
</div>
<p style="font-size:13px;color:var(--pn-muted);margin:0;">
    {{ __('bank-payments::settings.mode') }}: {{ __('bank-payments::settings.mode_test') }}.<br>
    {{ __('bank-payments::dashboard.test_mode_notice') }}
</p>
