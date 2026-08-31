@extends('admin.layouts.app')
@section('title', __('admin.settings.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('admin.settings.general_settings') }}</h1>
</div>
<form method="POST" action="{{ route('admin.settings.general.update') }}">
    @csrf

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.company_information') }}</strong></div>
        <div class="card-body">
            <div class="form-group"><label class="form-label">{{ __('common.form.company_name') }}</label><input type="text" name="CompanyName" value="{{ $settings['CompanyName'] ?? '' }}" class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.settings.domain') }}</label><input type="text" name="Domain" value="{{ $settings['Domain'] ?? '' }}" class="form-control" placeholder="yourdomain.com"></div>
            <div class="form-group"><label class="form-label">{{ __('admin.settings.company_address') }}</label><textarea name="Address" rows="3" class="form-control">{{ $settings['Address'] ?? '' }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.phone_number') }}</label><input type="text" name="PhoneNumber" value="{{ $settings['PhoneNumber'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.company_email') }}</label><input type="email" name="Email" value="{{ $settings['Email'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.city') }}</label><input type="text" name="CompanyCity" value="{{ $settings['CompanyCity'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.tax_id') }}</label><input type="text" name="TaxID" value="{{ $settings['TaxID'] ?? '' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.localization') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">{{ __('admin.settings.default_language') }}</label>
                    <select name="DefaultLanguage" class="form-control">
                        @foreach($languages as $lang)
                            <option value="{{ $lang->code }}" {{ ($settings['DefaultLanguage'] ?? '') === $lang->code ? 'selected' : '' }}>{{ $lang->native_name ?: $lang->name }}</option>
                        @endforeach
                    </select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.default_country') }}</label>
                    <select name="Country" class="form-control">
                        <option value="">&mdash;</option>
                        @foreach($countries as $code => $name)
                            <option value="{{ $code }}" {{ ($settings['Country'] ?? '') === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.date_format') }}</label><input type="text" name="DateFormat" value="{{ $settings['DateFormat'] ?? 'd/m/Y' }}" class="form-control" placeholder="d/m/Y"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.timezone') }}</label><input type="text" name="Timezone" value="{{ $settings['Timezone'] ?? 'UTC' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    {{-- Where the terms are. The registration form asks customers to agree to
         them and links both; with nowhere to enter the addresses the links
         went to "#". --}}
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.legal') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.tos_url') }}</label>
                    <input type="url" name="TOSUrl" value="{{ $settings['TOSUrl'] ?? '' }}" class="form-control" placeholder="https://example.com/terms">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.privacy_url') }}</label>
                    <input type="url" name="PrivacyUrl" value="{{ $settings['PrivacyUrl'] ?? '' }}" class="form-control" placeholder="https://example.com/privacy">
                </div>
            </div>
            <div style="font-size:12px;color:#777;">{{ __('admin.settings.legal_hint') }}</div>
        </div>
    </div>

    {{-- Automation. Suspension used to run on a grace period hard-coded at 3
         days; termination did not exist at all. Termination deletes data, so
         it ships OFF and only ever touches services suspended over an unpaid
         invoice (see TerminationCommand for the exact rules). --}}
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.automation') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.auto_suspension_days') }}</label>
                    <input type="number" min="0" name="AutoSuspensionDays" value="{{ $settings['AutoSuspensionDays'] ?? '3' }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.auto_termination_days') }}</label>
                    <input type="number" min="1" name="AutoTerminationDays" value="{{ $settings['AutoTerminationDays'] ?? '30' }}" class="form-control">
                </div>
            </div>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-top:5px;">
                <input type="checkbox" name="AutoTerminationEnabled" value="1" {{ !empty($settings['AutoTerminationEnabled']) ? 'checked' : '' }}>
                {{ __('admin.settings.auto_termination_label') }}
            </label>
            <div style="font-size:12px;color:#777;margin-top:8px;">{{ __('admin.settings.automation_hint') }}</div>
        </div>
    </div>

    {{-- External fraud screening. Advisory like the built-in rules: a missing
         key or an outage never blocks an order (see FraudDetectionService).
         The secret fields keep their stored value when left blank, the same
         contract as the mail password. --}}
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.fraud_screening') }}</strong></div>
        <div class="card-body">
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" name="MaxMindEnabled" value="1" {{ !empty($settings['MaxMindEnabled']) ? 'checked' : '' }}>
                {{ __('admin.settings.maxmind_enabled') }}
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:8px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.maxmind_account_id') }}</label>
                    <input type="text" name="MaxMindAccountId" value="{{ $settings['MaxMindAccountId'] ?? '' }}" class="form-control" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.maxmind_license_key') }}</label>
                    <input type="password" name="MaxMindLicenseKey" value="" class="form-control" autocomplete="new-password" placeholder="{{ !empty($settings['MaxMindLicenseKey']) ? '••••••••' : '' }}">
                </div>
            </div>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-top:10px;">
                <input type="checkbox" name="FraudLabsEnabled" value="1" {{ !empty($settings['FraudLabsEnabled']) ? 'checked' : '' }}>
                {{ __('admin.settings.fraudlabs_enabled') }}
            </label>
            <div class="form-group" style="margin-top:8px;max-width:calc(50% - 8px);">
                <label class="form-label">{{ __('admin.settings.fraudlabs_api_key') }}</label>
                <input type="password" name="FraudLabsApiKey" value="" class="form-control" autocomplete="new-password" placeholder="{{ !empty($settings['FraudLabsApiKey']) ? '••••••••' : '' }}">
            </div>
            <div style="font-size:12px;color:#777;margin-top:8px;">{{ __('admin.settings.fraud_screening_hint') }}</div>
        </div>
    </div>

    {{-- Twilio Verify. State (codes, attempts, expiry) lives at Twilio; the
         panel only records the moment a check comes back approved. --}}
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.sms_verification') }}</strong></div>
        <div class="card-body">
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" name="TwilioVerifyEnabled" value="1" {{ !empty($settings['TwilioVerifyEnabled']) ? 'checked' : '' }}>
                {{ __('admin.settings.twilio_enabled') }}
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-top:8px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.twilio_account_sid') }}</label>
                    <input type="text" name="TwilioAccountSid" value="{{ $settings['TwilioAccountSid'] ?? '' }}" class="form-control" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.twilio_auth_token') }}</label>
                    <input type="password" name="TwilioAuthToken" value="" class="form-control" autocomplete="new-password" placeholder="{{ !empty($settings['TwilioAuthToken']) ? '••••••••' : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.twilio_verify_service_sid') }}</label>
                    <input type="text" name="TwilioVerifyServiceSid" value="{{ $settings['TwilioVerifyServiceSid'] ?? '' }}" class="form-control" autocomplete="off">
                </div>
            </div>
            <div style="font-size:12px;color:#777;margin-top:8px;">{{ __('admin.settings.sms_verification_hint') }}</div>
        </div>
    </div>

    {{-- Late fees. The command that charges them has always read these three
         settings; there was nowhere to enter them, so it read "none" every
         morning and stopped. --}}
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.late_fees') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.late_fee_type') }}</label>
                    <select name="LateFeeType" class="form-control">
                        <option value="none" {{ ($settings['LateFeeType'] ?? 'none') === 'none' ? 'selected' : '' }}>{{ __('admin.settings.late_fee_none') }}</option>
                        <option value="flat" {{ ($settings['LateFeeType'] ?? '') === 'flat' ? 'selected' : '' }}>{{ __('admin.settings.late_fee_flat') }}</option>
                        <option value="percent" {{ ($settings['LateFeeType'] ?? '') === 'percent' ? 'selected' : '' }}>{{ __('admin.settings.late_fee_percent') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.late_fee_amount') }}</label>
                    <input type="number" step="0.01" min="0" name="LateFeeAmount" value="{{ $settings['LateFeeAmount'] ?? '0' }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.late_fee_min_days') }}</label>
                    <input type="number" min="0" name="LateFeeMinDays" value="{{ $settings['LateFeeMinDays'] ?? '3' }}" class="form-control">
                </div>
            </div>
            <div style="font-size:12px;color:#777;">{{ __('admin.settings.late_fee_hint') }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.system_settings') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">{{ __('admin.settings.admin_login_prefix') }}</label><input type="text" name="AdminDir" value="{{ $settings['AdminDir'] ?? 'admin' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.client_area_template') }}</label><input type="text" name="ActiveClientAreaTemplate" value="{{ $settings['ActiveClientAreaTemplate'] ?? 'default' }}" class="form-control"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:5px;">
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="MaintenanceMode" value="1" {{ !empty($settings['MaintenanceMode']) ? 'checked' : '' }}> {{ __('admin.settings.maintenance_mode_label') }}</label>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="OrderFormDisplayedOn" value="orderforms" {{ ($settings['OrderFormDisplayedOn'] ?? '') === 'orderforms' ? 'checked' : '' }}> {{ __('admin.settings.enable_order_form') }}</label>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.invoices_section') }}</strong></div>
        <div class="card-body">
            <div class="form-group" style="max-width:50%;">
                <label class="form-label">{{ __('admin.settings.default_payment_method') }}</label>
                <select name="DefaultPaymentMethod" class="form-control">
                    @foreach($paymentMethods as $pm)
                    <option value="{{ $pm }}" {{ ($settings['DefaultPaymentMethod'] ?? '') === $pm ? 'selected' : '' }}>{{ \payment_method_label($pm) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-top:10px;max-width:50%;">
                <label class="form-label">{{ __('admin.settings.invoice_due_days') }}</label>
                <input type="number" name="InvoiceDueDays" value="{{ $settings['InvoiceDueDays'] ?? '14' }}" class="form-control" min="0">
            </div>
            <div class="form-group" style="margin-top:10px;max-width:50%;">
                <label class="form-label" for="invoice-number-format">{{ __('admin.settings.invoice_number_format') }}</label>
                <input type="text" id="invoice-number-format" name="InvoiceNumberFormat" value="{{ $settings['InvoiceNumberFormat'] ?? 'INV-{year}{month}-{num}' }}" class="form-control" placeholder="INV-{year}{month}-{num}">
            </div>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-top:6px;">
                <input type="checkbox" name="InvoiceNumberYearlyReset" value="1" {{ !empty($settings['InvoiceNumberYearlyReset']) && $settings['InvoiceNumberYearlyReset'] == '1' ? 'checked' : '' }}>
                {{ __('admin.settings.invoice_number_reset_year') }}
            </label>
            <div style="font-size:12px;color:#777;margin-top:6px;">
                {{ __('admin.settings.invoice_number_tokens') }}
            </div>
            <div style="font-size:13px;color:#555;margin-top:6px;">
                <span>{{ __('admin.settings.invoice_number_last') }}:</span>
                <code style="background:#f5f5f5;padding:1px 5px;border-radius:3px;">{{ $invoiceLast ?? '—' }}</code>
                <span style="margin-left:14px;">{{ __('admin.settings.invoice_number_preview') }}:</span>
                <code id="invoice-number-preview" style="background:#f5f5f5;padding:1px 5px;border-radius:3px;font-weight:600;">{{ $invoicePreview }}</code>
            </div>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-top:14px;font-weight:600;">
                <input type="checkbox" id="proforma-enabled" name="ProformaEnabled" value="1" onchange="document.getElementById('proforma-scheme').style.display = this.checked ? '' : 'none';" {{ $proformaEnabled ? 'checked' : '' }}>
                {{ __('admin.settings.proforma_enabled') }}
            </label>
            <div id="proforma-scheme" style="{{ $proformaEnabled ? '' : 'display:none;' }}">
                <div class="form-group" style="margin-top:10px;max-width:50%;">
                    <label class="form-label" for="proforma-number-format">{{ __('admin.settings.proforma_number_format') }}</label>
                    <input type="text" id="proforma-number-format" name="ProformaNumberFormat" value="{{ $proformaFormat }}" class="form-control" placeholder="PRO-{year}/{month}-{num}">
                    <div style="font-size:13px;color:#555;margin-top:6px;">
                        <span>{{ __('admin.settings.invoice_number_last') }}:</span>
                        <code style="background:#f5f5f5;padding:1px 5px;border-radius:3px;">{{ $proformaLast ?? '—' }}</code>
                        <span style="margin-left:14px;">{{ __('admin.settings.invoice_number_preview') }}:</span>
                        <code style="background:#f5f5f5;padding:1px 5px;border-radius:3px;font-weight:600;">{{ $proformaPreview }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.mail_configuration') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:10px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.mail_type') }}</label>
                    <select name="MailType" id="mail-type" class="form-control">
                        <option value="php_mail" {{ ($settings['MailType'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' }}>{{ __('admin.settings.php_mail') }}</option>
                        <option value="smtp" {{ ($settings['MailType'] ?? '') === 'smtp' ? 'selected' : '' }}>{{ __('admin.settings.smtp') }}</option>
                    </select>
                    <div style="color:#777;font-size:12px;margin-top:6px;">
                        {{ __('admin.settings.sending_via', ['transport' => $mailTransport ?? config('mail.default')]) }}
                        @if(($mailTransport ?? config('mail.default')) === 'log')
                            <strong style="color:#c00;">{{ __('admin.settings.mail_goes_to_log') }}</strong>
                        @endif
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.enable_email_sending') }}</label>
                    <div style="padding-top:8px;">
                        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="MailEnabled" value="1" {{ !empty($settings['MailEnabled']) && $settings['MailEnabled'] == '1' ? 'checked' : '' }}>
                            {{ __('admin.settings.enable_outgoing_emails') }}
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:10px;">
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.system_email_address') }}</label>
                    <input type="email" name="SystemEmailAddress" value="{{ $settings['SystemEmailAddress'] ?? '' }}" class="form-control" placeholder="noreply@yourdomain.com">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.settings.email_from_name') }}</label>
                    {{-- Not "Your Company Name": that placeholder made this look like a
                         fourth place to type the company name. It is the sender line
                         on outgoing mail, nothing more. --}}
                    <input type="text" name="EmailFromName" value="{{ $settings['EmailFromName'] ?? '' }}" class="form-control" placeholder="e.g. MyHosting Billing">
                </div>
            </div>

            <div id="smtp-fields" style="display:{{ ($settings['MailType'] ?? 'php_mail') === 'smtp' ? 'block' : 'none' }};">
                <hr style="margin:10px 0;">
                <div style="font-size:12px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">{{ __('admin.settings.smtp_configuration') }}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.smtp_host') }}</label>
                        <input type="text" name="SMTPHost" value="{{ $settings['SMTPHost'] ?? '' }}" class="form-control" placeholder="smtp.yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.smtp_port') }}</label>
                        <input type="number" name="SMTPPort" value="{{ $settings['SMTPPort'] ?? '587' }}" class="form-control" placeholder="587">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.smtp_username') }}</label>
                        <input type="text" name="SMTPUsername" value="{{ $settings['SMTPUsername'] ?? '' }}" class="form-control" placeholder="user@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.smtp_password') }}</label>
                        {{-- Never sent back: the stored password used to sit in the page source of
                             every settings page load. Left empty, it stays as it is. --}}
                        <input type="password" name="SMTPPassword" value="" autocomplete="new-password" class="form-control" placeholder="{{ ($settings['SMTPPassword'] ?? '') !== '' ? __('admin.settings.smtp_password_keep') : '' }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.settings.smtp_encryption') }}</label>
                        <select name="SMTPSecurity" class="form-control">
                            <option value="tls" {{ ($settings['SMTPSecurity'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS (STARTTLS)</option>
                            <option value="ssl" {{ ($settings['SMTPSecurity'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="none" {{ ($settings['SMTPSecurity'] ?? '') === 'none' ? 'selected' : '' }}>None</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr style="margin:14px 0;">
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" id="test-email-btn" class="btn btn-secondary">{{ __('admin.settings.send_test_email') }}</button>
                <span id="test-email-result" style="font-size:13px;"></span>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.settings.domains_section') }}</strong></div>
        <div class="card-body">
            <label class="form-label">{{ __('admin.settings.default_nameservers') }}</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:8px;">
                @for($i = 1; $i <= 5; $i++)
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">NS{{ $i }}</label>
                    <input type="text" name="DefaultNameserver{{ $i }}" value="{{ $settings['DefaultNameserver'.$i] ?? '' }}" class="form-control" placeholder="ns{{ $i }}.yourdomain.com">
                </div>
                @endfor
            </div>
            <div style="font-size:12px;color:#777;">{{ __('admin.settings.default_nameservers_hint') }}</div>
        </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
        <button type="submit" class="btn btn-primary">{{ __('admin.settings.save_settings') }}</button>
    </div>
</form>

<script>
document.getElementById('invoice-number-format').addEventListener('input', renderInvoicePreview);
document.addEventListener('DOMContentLoaded', renderInvoicePreview);

function renderInvoicePreview() {
    var fmt = document.getElementById('invoice-number-format').value || '';
    var now = new Date();
    var y = String(now.getFullYear());
    var yy = y.slice(-2);
    var m = ('0' + (now.getMonth() + 1)).slice(-2);
    var d = ('0' + now.getDate()).slice(-2);
    var seq = String({{ $invoiceNextSeq }});
    while (seq.length < 6) { seq = '0' + seq; }
    var out = fmt
        .replace(/\{year\}/g, y)
        .replace(/\{yy\}/g, yy)
        .replace(/\{month\}/g, m)
        .replace(/\{day\}/g, d)
        .replace(/\{num\}/g, seq);
    document.getElementById('invoice-number-preview').textContent = out;
}

document.getElementById('mail-type').addEventListener('change', function() {
    document.getElementById('smtp-fields').style.display = this.value === 'smtp' ? 'block' : 'none';
});

document.getElementById('test-email-btn').addEventListener('click', function() {
    var btn = this;
    var result = document.getElementById('test-email-result');
    btn.disabled = true;
    btn.textContent = '{{ __("admin.settings.sending") }}';
    result.textContent = '';
    result.style.color = '';

    fetch('{{ route('admin.settings.test-email') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = '{{ __("admin.settings.send_test_email") }}';
        if (data.success) {
            result.textContent = '✓ ' + data.message;
            result.style.color = '#3c763d';
        } else {
            result.textContent = '✗ ' + data.message;
            result.style.color = '#a94442';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '{{ __("admin.settings.send_test_email") }}';
        result.textContent = '✗ {{ __("admin.settings.request_failed") }}';
        result.style.color = '#a94442';
    });
});
</script>
@endsection
