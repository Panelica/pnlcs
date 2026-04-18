@extends("admin.layouts.app")
@section("title", __("admin.mass_email_bulk_actions"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.bulk.title') }}</h1>
</div>

{{-- Tabs --}}
<div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #ddd;">
    <button type="button" class="bulk-tab active" onclick="switchBulkTab('email', this)" style="padding:10px 20px;font-size:13px;font-weight:600;border:none;background:var(--theme-primary, #1a4d80);color:#fff;cursor:pointer;border-radius:4px 4px 0 0;">{{ __('admin.bulk.mass_email') }}</button>
    <button type="button" class="bulk-tab" onclick="switchBulkTab('invoice', this)" style="padding:10px 20px;font-size:13px;font-weight:600;border:none;background:#f5f5f5;color:#333;cursor:pointer;border-radius:4px 4px 0 0;">{{ __('admin.bulk.bulk_invoice') }}</button>
    <button type="button" class="bulk-tab" onclick="switchBulkTab('service', this)" style="padding:10px 20px;font-size:13px;font-weight:600;border:none;background:#f5f5f5;color:#333;cursor:pointer;border-radius:4px 4px 0 0;">{{ __('admin.bulk.bulk_service_update') }}</button>
</div>

{{-- Mass Email Tab --}}
<div id="tab-email" class="bulk-panel card" style="border-top:none;border-radius:0 0 4px 4px;">
    <form method="POST" action="{{ route('admin.bulk.mass-email.send') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">{{ __('admin.bulk.select_recipients') }}</label>
                <div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:6px;cursor:pointer;font-weight:600;color:var(--theme-primary, #1a4d80);">
                        <input type="checkbox" id="select-all-email" onchange="document.querySelectorAll('#tab-email input[name=\client_ids[]\]').forEach(c=>c.checked=this.checked)"> {{ __('admin.bulk.select_all') }} ({{ count($clients) }})
                    </label>
                    @foreach($clients as $client)
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:2px;cursor:pointer;">
                        <input type="checkbox" name="client_ids[]" value="{{ $client->id }}"> {{ $client->first_name }} {{ $client->last_name }} ({{ $client->email }})
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('common.form.subject') }}</label><input type="text" name="subject" required class="form-control" placeholder="{{ __('admin.bulk.email_subject_placeholder') }}"></div>
            <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('common.form.message') }}</label><textarea name="message" required class="form-control" rows="6" placeholder="{{ __('admin.bulk.email_body_placeholder') }}"></textarea></div>
        </div>
        <div style="padding:12px 16px;border-top:1px solid #eee;text-align:right;">
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('{{ __('admin.bulk.confirm_send') }}')">{{ __('admin.bulk.send_mass_email') }}</button>
        </div>
    </form>
</div>

{{-- Bulk Invoice Tab --}}
<div id="tab-invoice" class="bulk-panel card" style="display:none;border-top:none;border-radius:0 0 4px 4px;">
    <form method="POST" action="{{ route('admin.bulk.invoice') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">{{ __('admin.bulk.select_clients') }}</label>
                <div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:6px;cursor:pointer;font-weight:600;color:var(--theme-primary, #1a4d80);">
                        <input type="checkbox" onchange="document.querySelectorAll('#tab-invoice input[name=\client_ids[]\]').forEach(c=>c.checked=this.checked)"> {{ __('admin.bulk.select_all') }} ({{ count($clients) }})
                    </label>
                    @foreach($clients as $client)
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:2px;cursor:pointer;">
                        <input type="checkbox" name="client_ids[]" value="{{ $client->id }}"> {{ $client->first_name }} {{ $client->last_name }}
                    </label>
                    @endforeach
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><input type="text" name="description" required class="form-control" placeholder="{{ __('admin.bulk.invoice_desc_placeholder') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.amount') }}</label><input type="number" name="amount" step="0.01" min="0.01" required class="form-control" placeholder="0.00"></div>
            </div>
            <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('admin.bulk.due_date') }}</label><input type="date" name="due_date" required class="form-control"></div>
        </div>
        <div style="padding:12px 16px;border-top:1px solid #eee;text-align:right;">
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('{{ __('admin.bulk.confirm_invoice') }}')">{{ __('admin.bulk.create_bulk_invoices') }}</button>
        </div>
    </form>
</div>

{{-- Bulk Service Update Tab --}}
<div id="tab-service" class="bulk-panel card" style="display:none;border-top:none;border-radius:0 0 4px 4px;">
    <form method="POST" action="{{ route('admin.bulk.service-update') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">{{ __('admin.bulk.select_services') }}</label>
                @php $services = \App\Models\Service::with("client")->orderBy("id", "desc")->limit(200)->get(); @endphp
                <div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:6px;cursor:pointer;font-weight:600;color:var(--theme-primary, #1a4d80);">
                        <input type="checkbox" onchange="document.querySelectorAll('#tab-service input[name=\service_ids[]\]').forEach(c=>c.checked=this.checked)"> {{ __('admin.bulk.select_all') }} ({{ count($services) }})
                    </label>
                    @foreach($services as $svc)
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:2px;cursor:pointer;">
                        <input type="checkbox" name="service_ids[]" value="{{ $svc->id }}"> #{{ $svc->id }} - {{ $svc->domain ?? "N/A" }} ({{ $svc->client?->first_name }} {{ $svc->client?->last_name }}) <span class="badge badge-{{ $svc->status === "active" ? "active" : "suspended" }}" style="font-size:10px;">{{ $svc->status }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">{{ __('admin.bulk.new_status') }}</label>
                <select name="status" required class="form-control">
                    <option value="active">{{ __('common.status.active') }}</option>
                    <option value="suspended">{{ __('common.status.suspended') }}</option>
                    <option value="terminated">{{ __('common.status.terminated') }}</option>
                    <option value="cancelled">{{ __('common.status.cancelled') }}</option>
                </select>
            </div>
        </div>
        <div style="padding:12px 16px;border-top:1px solid #eee;text-align:right;">
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('{{ __('admin.bulk.confirm_service_update') }}')">{{ __('admin.bulk.update_services') }}</button>
        </div>
    </form>
</div>

<script>
function switchBulkTab(tab, btn) {
    document.querySelectorAll('.bulk-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.bulk-tab').forEach(b => { b.style.background = '#f5f5f5'; b.style.color = '#333'; });
    document.getElementById('tab-' + tab).style.display = 'block';
    btn.style.background = 'var(--theme-primary, #1a4d80)';
    btn.style.color = '#fff';
}
</script>

@endsection
