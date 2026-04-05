@extends("admin.layouts.app")
@section("title", __("admin.notification_channels"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Notification Channels</h1>
    <button type="button" onclick="document.getElementById('modal-add-provider').style.display='flex'" class="btn btn-primary btn-sm">+ Add Provider</button>
</div>

{{-- Providers --}}
<div class="card">
    <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;">
        <i class="fas fa-bell"></i> Notification Providers
    </div>
    @if($providers->isEmpty())
        <div class="card-body" style="text-align:center;padding:40px;color:#999;">No notification providers configured. Click "Add Provider" to create one.</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.name') }}</th>
            <th>{{ __('common.table.type') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th>Rules</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($providers as $provider)
        <tr>
            <td style="font-weight:600;">{{ $provider->name }}</td>
            <td>
                @if($provider->type === 'email')
                    <span class="badge badge-active"><i class="fas fa-envelope"></i> Email</span>
                @elseif($provider->type === 'slack')
                    <span class="badge badge-pending"><i class="fab fa-slack"></i> Slack</span>
                @else
                    <span class="badge badge-open"><i class="fas fa-globe"></i> Webhook</span>
                @endif
            </td>
            <td>
                @if($provider->active)
                    <span class="badge badge-active">Active</span>
                @else
                    <span class="badge badge-open">Inactive</span>
                @endif
            </td>
            <td>{{ $provider->rules->count() }} rule(s)</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs" onclick="editProvider({{ $provider->id }}, {{ json_encode($provider) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.notification-providers.destroy', $provider->id) }}" style="display:inline;" onsubmit="return confirm('Delete this provider and all its rules?')">
                    @csrf @method("DELETE")
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- Rules --}}
<div style="margin-top:20px;">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h2 style="font-size:18px;">Notification Rules</h2>
        @if($providers->isNotEmpty())
        <button type="button" onclick="document.getElementById('modal-add-rule').style.display='flex'" class="btn btn-primary btn-sm">+ Add Rule</button>
        @endif
    </div>
    <div class="card">
        @php
            $allRules = $providers->flatMap(fn($p) => $p->rules->map(fn($r) => (object) array_merge($r->toArray(), ['provider_name' => $p->name, 'provider_type' => $p->type])));
        @endphp
        @if($allRules->isEmpty())
            <div class="card-body" style="text-align:center;padding:40px;color:#999;">No notification rules yet. Add a provider first, then create rules.</div>
        @else
        <table class="data-table">
            <thead><tr>
                <th>Event</th>
                <th>Provider</th>
                <th>{{ __('common.table.status') }}</th>
                <th style="text-align:right;">{{ __('common.table.actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($allRules as $rule)
            <tr>
                <td style="font-weight:600;">{{ $rule->event }}</td>
                <td>{{ $rule->provider_name }} ({{ $rule->provider_type }})</td>
                <td>
                    @if($rule->active)
                        <span class="badge badge-active">Active</span>
                    @else
                        <span class="badge badge-open">Inactive</span>
                    @endif
                </td>
                <td style="text-align:right;">
                    <form method="POST" action="{{ route('admin.config.notification-rules.destroy', $rule->id) }}" style="display:inline;" onsubmit="return confirm('Delete this rule?')">
                        @csrf @method("DELETE")
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

{{-- Add Provider Modal --}}
<div id="modal-add-provider" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-provider').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Notification Provider</h4>
            <button type="button" onclick="document.getElementById('modal-add-provider').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.notification-providers.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Provider Name *</label><input type="text" name="name" required class="form-control" placeholder="e.g. Slack Alerts"></div>
                <div class="form-group"><label class="form-label">Type *</label>
                    <select name="type" required class="form-control" onchange="toggleProviderFields(this.value, 'add')">
                        <option value="email">Email</option>
                        <option value="slack">Slack</option>
                        <option value="webhook">Webhook</option>
                    </select>
                </div>
                <div id="add-slack-fields" style="display:none;">
                    <div class="form-group"><label class="form-label">Slack Webhook URL</label><input type="url" name="settings[webhook_url]" class="form-control" placeholder="https://hooks.slack.com/services/..."></div>
                    <div class="form-group"><label class="form-label">Bot Username</label><input type="text" name="settings[username]" class="form-control" placeholder="PNLCS" value="PNLCS"></div>
                </div>
                <div id="add-webhook-fields" style="display:none;">
                    <div class="form-group"><label class="form-label">Webhook URL</label><input type="url" name="settings[url]" class="form-control" placeholder="https://example.com/webhook"></div>
                    <div class="form-group"><label class="form-label">Secret (optional)</label><input type="text" name="settings[secret]" class="form-control" placeholder="Shared secret for verification"></div>
                </div>
                <div class="form-group">
                    <label class="form-label"><input type="checkbox" name="active" value="1" checked> Active</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-provider').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Provider</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Provider Modal --}}
<div id="modal-edit-provider" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-provider').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Edit Provider</h4>
            <button type="button" onclick="document.getElementById('modal-edit-provider').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form id="edit-provider-form" method="POST" action="">
            @csrf @method("PUT")
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Provider Name *</label><input type="text" name="name" id="edit-prov-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Type *</label>
                    <select name="type" id="edit-prov-type" required class="form-control" onchange="toggleProviderFields(this.value, 'edit')">
                        <option value="email">Email</option>
                        <option value="slack">Slack</option>
                        <option value="webhook">Webhook</option>
                    </select>
                </div>
                <div id="edit-slack-fields" style="display:none;">
                    <div class="form-group"><label class="form-label">Slack Webhook URL</label><input type="url" name="settings[webhook_url]" id="edit-slack-url" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Bot Username</label><input type="text" name="settings[username]" id="edit-slack-user" class="form-control"></div>
                </div>
                <div id="edit-webhook-fields" style="display:none;">
                    <div class="form-group"><label class="form-label">Webhook URL</label><input type="url" name="settings[url]" id="edit-wh-url" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Secret</label><input type="text" name="settings[secret]" id="edit-wh-secret" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="form-label"><input type="checkbox" name="active" value="1" id="edit-prov-active"> Active</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-provider').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.update') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Rule Modal --}}
<div id="modal-add-rule" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-rule').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Notification Rule</h4>
            <button type="button" onclick="document.getElementById('modal-add-rule').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.notification-rules.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Provider *</label>
                    <select name="provider_id" required class="form-control">
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->type }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Event Type *</label>
                    <select name="event" required class="form-control">
                        @foreach($eventTypes as $ev)
                            <option value="{{ $ev }}">{{ $ev }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Recipient Email (for email providers)</label>
                    <input type="email" name="conditions[recipient_email]" class="form-control" placeholder="admin@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label"><input type="checkbox" name="active" value="1" checked> Active</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-rule').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Rule</button>
            </div>
        </form>
    </div>
</div>

@push("scripts")
<script>
function toggleProviderFields(type, prefix) {
    document.getElementById(prefix + '-slack-fields').style.display = type === 'slack' ? 'block' : 'none';
    document.getElementById(prefix + '-webhook-fields').style.display = type === 'webhook' ? 'block' : 'none';
}
function editProvider(id, data) {
    document.getElementById('edit-provider-form').action = '/admin/config/notification-providers/' + id;
    document.getElementById('edit-prov-name').value = data.name;
    document.getElementById('edit-prov-type').value = data.type;
    document.getElementById('edit-prov-active').checked = data.active;
    var s = data.settings || {};
    document.getElementById('edit-slack-url').value = s.webhook_url || '';
    document.getElementById('edit-slack-user').value = s.username || 'PNLCS';
    document.getElementById('edit-wh-url').value = s.url || '';
    document.getElementById('edit-wh-secret').value = s.secret || '';
    toggleProviderFields(data.type, 'edit');
    document.getElementById('modal-edit-provider').style.display = 'flex';
}
</script>
@endpush
@endsection
