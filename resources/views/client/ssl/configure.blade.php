@extends("client.layouts.app")

@section("title", __("client.configure_ssl_certificate"))

@section("content")
<div class="mb-4">
    <a href="{{ route('client.ssl.show', $order) }}" class="btn btn-sm btn-secondary">&larr; Back</a>
</div>

<h1 class="h3 mb-4">Configure SSL Certificate</h1>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('client.ssl.submitConfiguration', $order) }}" x-data="sslConfigForm()">
    @csrf

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Certificate Signing Request (CSR)</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">CSR <span class="text-danger">*</span></label>
                <textarea name="csr" class="form-control font-monospace" rows="8" required
                    placeholder="-----BEGIN CERTIFICATE REQUEST-----&#10;Paste your CSR here...&#10;-----END CERTIFICATE REQUEST-----"
                    x-model="csr" @blur="decodeCsr()">{{ old('csr') }}</textarea>
                <small class="text-muted">Paste your Certificate Signing Request (CSR) or generate one using your web server.</small>
            </div>

            <div x-show="csrDecoded" x-cloak class="alert alert-info">
                <strong>CSR Decoded:</strong>
                <div class="row mt-2">
                    <div class="col-md-6"><small>Common Name:</small> <strong x-text="csrInfo.cn"></strong></div>
                    <div class="col-md-6"><small>Organization:</small> <span x-text="csrInfo.org"></span></div>
                    <div class="col-md-6"><small>Country:</small> <span x-text="csrInfo.country"></span></div>
                    <div class="col-md-6"><small>Key Size:</small> <span x-text="csrInfo.key_size"></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Server & Validation</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Web Server Type <span class="text-danger">*</span></label>
                    <select name="webserver_type" class="form-select" required>
                        <option value="">Select server type...</option>
                        @foreach($webServerTypes as $id => $name)
                            <option value="{{ $id }}" {{ old('webserver_type') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Domain Validation Method <span class="text-danger">*</span></label>
                    <select name="validation_method" class="form-select" required x-model="validationMethod" @change="onValidationChange()">
                        <option value="EMAIL">Email Validation</option>
                        <option value="HTTP">HTTP File Validation</option>
                        <option value="DNS">DNS CNAME Validation</option>
                    </select>
                </div>
            </div>

            <div x-show="validationMethod === 'EMAIL'" class="mb-3">
                <label class="form-label">Approver Email <span class="text-danger">*</span></label>
                <select name="approver_email" class="form-select" x-model="approverEmail">
                    <option value="">Loading emails...</option>
                    <template x-for="email in approverEmails" :key="email">
                        <option :value="email" x-text="email"></option>
                    </template>
                </select>
                <small class="text-muted">Select the email address that will receive the domain validation email.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Additional SAN Domains</label>
                <textarea name="domains" class="form-control" rows="3" placeholder="One domain per line (optional)">{{ old('domains') }}</textarea>
                <small class="text-muted">Additional domains to include in the certificate (Subject Alternative Names).</small>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Admin Contact Information</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('common.form.first_name') }}<span class="text-danger">*</span></label>
                    <input type="text" name="admin_first_name" class="form-control" value="{{ old('admin_first_name', auth()->user()->first_name ?? '') }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('common.form.last_name') }}<span class="text-danger">*</span></label>
                    <input type="text" name="admin_last_name" class="form-control" value="{{ old('admin_last_name', auth()->user()->last_name ?? '') }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('common.form.email') }}<span class="text-danger">*</span></label>
                    <input type="email" name="admin_email" class="form-control" value="{{ old('admin_email', auth()->user()->email ?? '') }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="admin_phone" class="form-control" value="{{ old('admin_phone', auth()->user()->phone_number ?? '') }}">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Organization</label>
                    <input type="text" name="admin_org" class="form-control" value="{{ old('admin_org', auth()->user()->company_name ?? '') }}">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">{{ __('common.form.address') }}</label>
                    <input type="text" name="admin_address" class="form-control" value="{{ old('admin_address', auth()->user()->address1 ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">{{ __('common.form.city') }}</label>
                    <input type="text" name="admin_city" class="form-control" value="{{ old('admin_city', auth()->user()->city ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">State/Province</label>
                    <input type="text" name="admin_state" class="form-control" value="{{ old('admin_state', auth()->user()->state ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">ZIP/Postal Code</label>
                    <input type="text" name="admin_zip" class="form-control" value="{{ old('admin_zip', auth()->user()->postcode ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="admin_country" class="form-control" maxlength="2" placeholder="US" value="{{ old('admin_country', auth()->user()->country ?? '') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button type="submit" class="btn btn-primary btn-lg">Submit Configuration</button>
    </div>
</form>

@push('scripts')
<script>
function sslConfigForm() {
    return {
        csr: '{{ old("csr", "") }}',
        csrDecoded: false,
        csrInfo: { cn: '', org: '', country: '', key_size: '' },
        validationMethod: '{{ old("validation_method", "EMAIL") }}',
        approverEmail: '{{ old("approver_email", "") }}',
        approverEmails: [],
        async decodeCsr() {
            if (!this.csr || this.csr.length < 100) return;
            try {
                // Extract domain from CSR for approver emails
                const lines = this.csr.trim().split('\n');
                if (lines.length > 2) {
                    this.csrDecoded = true;
                    this.csrInfo.cn = 'Parsing...';
                    this.loadApproverEmails();
                }
            } catch (e) { console.error(e); }
        },
        async loadApproverEmails() {
            const domain = this.csrInfo.cn || '{{ $order->domain ?? "" }}';
            if (!domain || domain === 'Parsing...') {
                // Use order domain as fallback
                const fallbackDomain = '{{ $order->domain ?? "" }}';
                if (!fallbackDomain) return;
            }
            try {
                const response = await fetch('{{ route("client.ssl.approverEmails", $order) }}?domain=' + encodeURIComponent(domain || '{{ $order->domain ?? "" }}'));
                const data = await response.json();
                if (data.emails && data.emails.length) {
                    this.approverEmails = data.emails;
                    if (!this.approverEmail) this.approverEmail = data.emails[0];
                }
            } catch (e) { console.error(e); }
        },
        onValidationChange() {
            if (this.validationMethod === 'EMAIL' && this.approverEmails.length === 0) {
                this.loadApproverEmails();
            }
        },
        init() {
            this.loadApproverEmails();
        }
    }
}
</script>
@endpush
@endsection
