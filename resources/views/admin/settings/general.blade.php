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
                <div class="form-group"><label class="form-label">{{ __('admin.settings.default_language') }}</label><input type="text" name="DefaultLanguage" value="{{ $settings['DefaultLanguage'] ?? 'english' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.default_country') }}</label><input type="text" name="Country" value="{{ $settings['Country'] ?? '' }}" class="form-control" placeholder="US"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.date_format') }}</label><input type="text" name="DateFormat" value="{{ $settings['DateFormat'] ?? 'd/m/Y' }}" class="form-control" placeholder="d/m/Y"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.settings.timezone') }}</label><input type="text" name="Timezone" value="{{ $settings['Timezone'] ?? 'UTC' }}" class="form-control"></div>
            </div>
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
                    <input type="text" name="EmailFromName" value="{{ $settings['EmailFromName'] ?? '' }}" class="form-control" placeholder="Your Company Name">
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
                        <input type="password" name="SMTPPassword" value="{{ $settings['SMTPPassword'] ?? '' }}" class="form-control" placeholder="••••••••">
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
        </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
        <button type="submit" class="btn btn-primary">{{ __('admin.settings.save_settings') }}</button>
        <button type="button" id="test-email-btn" class="btn btn-secondary" style="margin-left:5px;">{{ __('admin.settings.send_test_email') }}</button>
        <span id="test-email-result" style="font-size:13px;"></span>
    </div>
</form>

<script>
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
