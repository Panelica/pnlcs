{{-- Panelica license section of the staff service page
     (Modules\Servers\PanelicaLicense, drawn through ModuleScreens). --}}
@php
    $fmt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format(date_fmt().' H:i') : '—';
    $row = 'display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:13px;';
    $server = fn ($host, $ip) => trim(($host ?? '').' '.(filled($ip) ? '('.$ip.')' : '')) ?: '—';
@endphp
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong>{{ __('panelica_license.admin.title') }}</strong>
        <span class="badge badge-{{ $state === 'licensed' ? 'active' : ($state === 'ended' ? 'terminated' : 'pending') }}">{{ __('panelica_license.state.'.$state) }}</span>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));gap:18px;">
        <div>
            <div style="font-size:12px;font-weight:700;color:#777;text-transform:uppercase;margin-bottom:6px;">{{ __('panelica_license.admin.license') }}</div>
            @if(! empty($data['license_id']))
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.key') }}</span><span style="font-family:monospace;">{{ $data['license_key'] ?? '—' }}</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.admin.central_id') }}</span><span style="font-family:monospace;font-size:12px;">{{ $data['license_id'] }}</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.plan') }}</span><span>{{ $data['license_plan'] ?? '—' }} @if(! empty($data['license_cycle']))<span style="color:#999;">· {{ $data['license_cycle'] }}</span>@endif</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.status') }}</span><span>{{ $data['license_status'] ?? '—' }}@if(! empty($data['suspended_here'])) <span style="color:#999;">({{ __('panelica_license.admin.suspended_here') }})</span>@endif</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.valid_until') }}</span><span>{{ $fmt($data['license_expires_at'] ?? null) }}</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.server') }}</span><span>{{ $server($data['license_server_hostname'] ?? null, $data['license_server_ip'] ?? null) }}</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.field.last_seen') }}</span><span>{{ $fmt($data['license_heartbeat_at'] ?? null) }}</span></div>
            <div style="{{ $row }}border-bottom:none;"><span style="color:#777;">{{ __('panelica_license.admin.licensed_at') }}</span><span>{{ $fmt($data['licensed_at'] ?? null) }}</span></div>
            @else
            <p style="font-size:13px;color:#777;margin:0 0 10px;">{{ __('panelica_license.admin.not_bought') }}</p>
            @if($state === 'waiting' || $state === 'none')
            <form method="POST" action="{{ route('admin.services.panelica-license.attach', $service) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                @csrf
                <input type="text" name="license_id" required maxlength="64" class="form-control" style="flex:1 1 220px;font-family:monospace;font-size:12px;" placeholder="{{ __('panelica_license.admin.attach_placeholder') }}" aria-label="{{ __('panelica_license.admin.central_id') }}">
                <button type="submit" class="btn btn-default btn-sm">{{ __('panelica_license.action.attach') }}</button>
            </form>
            <small style="color:#999;display:block;margin-top:4px;">{{ __('panelica_license.admin.attach_hint') }}</small>
            @endif
            @endif
        </div>

        <div>
            <div style="font-size:12px;font-weight:700;color:#777;text-transform:uppercase;margin-bottom:6px;">{{ __('panelica_license.admin.install') }}</div>
            @if($command)
            <div style="margin-bottom:8px;">
                <div style="font-size:12px;color:#777;margin-bottom:4px;">{{ __('panelica_license.admin.customer_command') }}</div>
                <code style="display:block;background:#0f172a;color:#e2e8f0;padding:8px 10px;border-radius:5px;font-size:12px;white-space:nowrap;overflow-x:auto;">{{ $command }}</code>
            </div>
            @endif
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.admin.script_fetched') }}</span><span>{{ $fmt($data['script_fetched_at'] ?? null) }}</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.admin.server_seen') }}</span><span>{{ $install ? $server($install['hostname'] ?? null, $install['ip_address'] ?? null) : '—' }}@if(! empty($install['os']))<br><small style="color:#999;">{{ $install['os'] }}</small>@endif</span></div>
            <div style="{{ $row }}"><span style="color:#777;">{{ __('panelica_license.admin.install_status') }}</span><span>{{ $install['status'] ?? '—' }}@if(($data['installs_seen'] ?? 0) > 1) <span style="color:#b45309;">· {{ __('panelica_license.admin.more_servers', ['count' => (int) $data['installs_seen']]) }}</span>@endif</span></div>
            <div style="{{ $row }}border-bottom:none;"><span style="color:#777;">{{ __('panelica_license.admin.checked') }}</span><span>{{ $fmt($data['checked_at'] ?? null) }}</span></div>
            @if(! empty($install['note']))
            <div style="margin-top:8px;padding:8px 10px;border-radius:5px;background:#fff7e6;border:1px solid #f5d38a;font-size:12.5px;color:#7a5200;">{{ $install['note'] }}</div>
            @endif
            @if($waitingRenewal ?? null)
            <div style="margin-top:8px;padding:8px 10px;border-radius:5px;background:#fdecea;border:1px solid #f5c2c0;font-size:12.5px;color:#8a1f17;">
                <strong>{{ __('panelica_license.admin.renewal_waiting') }}</strong> {{ $waitingRenewal->last_error }}
            </div>
            @endif
            @if(! empty($data['last_error']))
            <div style="margin-top:8px;padding:8px 10px;border-radius:5px;background:#fdecea;border:1px solid #f5c2c0;font-size:12.5px;color:#8a1f17;">
                <strong>{{ __('panelica_license.admin.last_error') }}</strong> {{ $data['last_error'] }}
                <span style="color:#a55;">· {{ $fmt($data['last_error_at'] ?? null) }}</span>
            </div>
            @endif
        </div>
    </div>
</div>
