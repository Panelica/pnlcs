@extends('admin.layouts.app')
@section('title', 'General Settings')
@section('content')

<div class="page-header">
    <h1>General Settings</h1>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('admin.settings.general.update') }}">
    @csrf

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Company Information</strong></div>
        <div class="card-body">
            <div class="form-group"><label class="form-label">Company Name</label><input type="text" name="CompanyName" value="{{ $settings['CompanyName'] ?? '' }}" class="form-control"></div>
            <div class="form-group"><label class="form-label">Domain</label><input type="text" name="Domain" value="{{ $settings['Domain'] ?? '' }}" class="form-control" placeholder="yourdomain.com"></div>
            <div class="form-group"><label class="form-label">Company Address</label><textarea name="Address" rows="3" class="form-control">{{ $settings['Address'] ?? '' }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Phone Number</label><input type="text" name="PhoneNumber" value="{{ $settings['PhoneNumber'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Company Email</label><input type="email" name="Email" value="{{ $settings['Email'] ?? '' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Localization</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Default Language</label><input type="text" name="DefaultLanguage" value="{{ $settings['DefaultLanguage'] ?? 'english' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Default Country</label><input type="text" name="Country" value="{{ $settings['Country'] ?? '' }}" class="form-control" placeholder="US"></div>
                <div class="form-group"><label class="form-label">Date Format</label><input type="text" name="DateFormat" value="{{ $settings['DateFormat'] ?? 'd/m/Y' }}" class="form-control" placeholder="d/m/Y"></div>
                <div class="form-group"><label class="form-label">Timezone</label><input type="text" name="Timezone" value="{{ $settings['Timezone'] ?? 'UTC' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>System Settings</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Admin Login URL Prefix</label><input type="text" name="AdminDir" value="{{ $settings['AdminDir'] ?? 'admin' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Client Area Template</label><input type="text" name="ActiveClientAreaTemplate" value="{{ $settings['ActiveClientAreaTemplate'] ?? 'default' }}" class="form-control"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:5px;">
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="MaintenanceMode" value="1" {{ !empty($settings['MaintenanceMode']) ? 'checked' : '' }}> Maintenance Mode (client area offline)</label>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="OrderFormDisplayedOn" value="orderforms" {{ ($settings['OrderFormDisplayedOn'] ?? '') === 'orderforms' ? 'checked' : '' }}> Enable Online Order Form</label>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Mail Configuration</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:10px;">
                <div class="form-group">
                    <label class="form-label">Mail Type</label>
                    <select name="MailType" id="mail-type" class="form-control">
                        <option value="php_mail" {{ ($settings['MailType'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' }}>PHP Mail</option>
                        <option value="smtp" {{ ($settings['MailType'] ?? '') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Enable Email Sending</label>
                    <div style="padding-top:8px;">
                        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="MailEnabled" value="1" {{ !empty($settings['MailEnabled']) && $settings['MailEnabled'] == '1' ? 'checked' : '' }}>
                            Enable outgoing emails
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:10px;">
                <div class="form-group">
                    <label class="form-label">System Email Address</label>
                    <input type="email" name="SystemEmailAddress" value="{{ $settings['SystemEmailAddress'] ?? '' }}" class="form-control" placeholder="noreply@yourdomain.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Email From Name</label>
                    <input type="text" name="EmailFromName" value="{{ $settings['EmailFromName'] ?? '' }}" class="form-control" placeholder="Your Company Name">
                </div>
            </div>

            <div id="smtp-fields" style="display:{{ ($settings['MailType'] ?? 'php_mail') === 'smtp' ? 'block' : 'none' }};">
                <hr style="margin:10px 0;">
                <div style="font-size:12px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">SMTP Configuration</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="SMTPHost" value="{{ $settings['SMTPHost'] ?? '' }}" class="form-control" placeholder="smtp.yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="SMTPPort" value="{{ $settings['SMTPPort'] ?? '587' }}" class="form-control" placeholder="587">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="SMTPUsername" value="{{ $settings['SMTPUsername'] ?? '' }}" class="form-control" placeholder="user@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" name="SMTPPassword" value="{{ $settings['SMTPPassword'] ?? '' }}" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Encryption</label>
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
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <button type="button" id="test-email-btn" class="btn btn-secondary" style="margin-left:5px;">Send Test Email</button>
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
    btn.textContent = 'Sending...';
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
        btn.textContent = 'Send Test Email';
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
        btn.textContent = 'Send Test Email';
        result.textContent = '✗ Request failed';
        result.style.color = '#a94442';
    });
});
</script>
@endsection
