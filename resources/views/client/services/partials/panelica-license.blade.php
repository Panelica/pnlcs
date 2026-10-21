{{-- Panelica license section of the customer's service page
     (Modules\Servers\PanelicaLicense, drawn through ModuleScreens). --}}
@php
    $fmt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format(date_fmt()) : '—';
    $active = strtolower((string) $service->status) === 'active';
    $seenServer = $install && (filled($install['hostname'] ?? null) || filled($install['ip_address'] ?? null));
    $needsHelp = filled($data['last_error'] ?? null);
@endphp
<style>
    .pl-card{margin-bottom:22px}
    .pl-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .pl-head span{display:inline-flex;align-items:center;gap:8px}
    .pl-badge{font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:999px;border:1px solid var(--border);background:var(--bg);color:var(--muted)}
    .pl-badge.licensed{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.35);color:#059669}
    .pl-badge.waiting{background:rgba(251,191,36,.14);border-color:rgba(251,191,36,.4);color:#b45309}
    .pl-body{padding:18px 20px}
    .pl-lead{margin:0 0 16px;color:var(--muted);font-size:13.5px;line-height:1.55}
    .pl-steps{list-style:none;margin:0;padding:0;display:grid;gap:14px;counter-reset:pl}
    .pl-steps li{display:grid;grid-template-columns:30px minmax(0,1fr);gap:12px;align-items:start}
    .pl-steps li>div{min-width:0}
    .pl-steps li::before{counter-increment:pl;content:counter(pl);width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;background:var(--primary-light);color:var(--primary)}
    .pl-steps strong{display:block;font-size:14px;margin-bottom:3px}
    .pl-steps small{display:block;color:var(--muted);font-size:12.5px;line-height:1.5}
    .pl-cmd{display:flex;gap:8px;align-items:stretch;margin-top:8px;flex-wrap:wrap}
    .pl-cmd code{flex:1 1 280px;min-width:0;font-family:ui-monospace,Menlo,monospace;font-size:12.5px;background:#0f172a;color:#e2e8f0;padding:11px 13px;border-radius:9px;overflow-x:auto;white-space:nowrap}
    .pl-note{margin-top:16px;padding:11px 14px;border-radius:10px;font-size:13px;line-height:1.5;border:1px solid var(--border);background:var(--bg)}
    .pl-note.warn{border-color:rgba(251,191,36,.45);background:rgba(251,191,36,.1)}
    .pl-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    .pl-key{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
</style>

<div class="sv-panel pl-card" id="panelica-license">
    <div class="sv-ph pl-head">
        <span><i class="ri-shield-keyhole-line"></i>{{ __('panelica_license.client.title') }}</span>
        <span class="pl-badge {{ $state }}">{{ __('panelica_license.state.'.$state) }}</span>
    </div>

    @if($state === 'waiting')
    <div class="pl-body">
        <p class="pl-lead">{{ __('panelica_license.client.waiting_lead') }}</p>
        <ol class="pl-steps">
            <li><div><strong>{{ __('panelica_license.client.step_server') }}</strong><small>{{ __('panelica_license.client.step_server_hint') }}</small></div></li>
            <li><div>
                <strong>{{ __('panelica_license.client.step_run') }}</strong>
                @if($active && $command)
                <div class="pl-cmd">
                    <code id="pl-command">{{ $command }}</code>
                    <button type="button" class="pn-btn pn-btn-sm" data-pl-copy="pl-command" data-copied="{{ __('panelica_license.client.copied') }}">{{ __('panelica_license.client.copy') }}</button>
                </div>
                <small style="margin-top:6px">{{ __('panelica_license.client.step_run_hint') }}</small>
                @else
                <small>{{ __('panelica_license.client.link_paused') }}</small>
                @endif
            </div></li>
            <li><div><strong>{{ __('panelica_license.client.step_wizard') }}</strong><small>{{ __('panelica_license.client.step_wizard_hint') }}</small></div></li>
        </ol>

        @if($needsHelp)
        <div class="pl-note warn">{{ __('panelica_license.client.needs_help') }}</div>
        @elseif($seenServer)
        <div class="pl-note">{{ __('panelica_license.client.server_seen', ['server' => trim(($install['hostname'] ?? '').' '.(($install['ip_address'] ?? '') !== '' ? '('.$install['ip_address'].')' : ''))]) }}</div>
        @elseif(! empty($data['script_fetched_at']))
        <div class="pl-note">{{ __('panelica_license.client.install_started', ['time' => $fmt($data['script_fetched_at'])]) }}</div>
        @endif

        @if($active)
        <div class="pl-actions">
            <form method="POST" action="{{ route('client.services.module-function', [$service, 'checkNow']) }}">@csrf
                <button type="submit" class="pn-btn pn-btn-sm pn-btn-primary"><i class="ri-refresh-line"></i> {{ __('panelica_license.action.check_now') }}</button>
            </form>
            @if($canChangeLink)
            <form method="POST" action="{{ route('client.services.module-function', [$service, 'newInstallLink']) }}" onsubmit="return pnConfirm(event, @js(__('panelica_license.client.new_link_confirm')))">@csrf
                <button type="submit" class="pn-btn pn-btn-sm">{{ __('panelica_license.action.new_link') }}</button>
            </form>
            @endif
        </div>
        @endif
    </div>

    @elseif($state === 'licensed')
    <ul class="sv-dl">
        <li><span class="k">{{ __('panelica_license.field.key') }}</span><span class="v pl-key">
            <span class="sv-code" id="pl-key">{{ $data['license_key'] ?? '—' }}</span>
            @if(! empty($data['license_key']))
            <button type="button" class="pn-btn pn-btn-sm" data-pl-copy="pl-key" data-copied="{{ __('panelica_license.client.copied') }}">{{ __('panelica_license.client.copy') }}</button>
            @endif
        </span></li>
        <li><span class="k">{{ __('panelica_license.field.plan') }}</span><span class="v">{{ $data['license_plan'] ?? '—' }}</span></li>
        <li><span class="k">{{ __('panelica_license.field.status') }}</span><span class="v">{{ __('panelica_license.license_status.'.(in_array($data['license_status'] ?? '', ['active', 'suspended', 'expired', 'cancelled', 'terminated', 'pending'], true) ? $data['license_status'] : 'unknown')) }}</span></li>
        <li><span class="k">{{ __('panelica_license.field.valid_until') }}</span><span class="v">{{ $fmt($data['license_expires_at'] ?? null) }}</span></li>
        <li><span class="k">{{ __('panelica_license.field.server') }}</span><span class="v">{{ trim(($data['license_server_hostname'] ?? '').' '.(($data['license_server_ip'] ?? '') !== '' ? '('.$data['license_server_ip'].')' : '')) ?: '—' }}</span></li>
        <li><span class="k">{{ __('panelica_license.field.last_seen') }}</span><span class="v">{{ $fmt($data['license_heartbeat_at'] ?? null) }}</span></li>
    </ul>
    <div class="pl-body" style="padding-top:0">
        <div class="pl-note">{{ __('panelica_license.client.licensed_hint') }}</div>
    </div>

    @elseif($state === 'ended')
    <div class="pl-body"><p class="pl-lead" style="margin:0">{{ __('panelica_license.client.ended') }}</p></div>

    @else
    <div class="pl-body"><p class="pl-lead" style="margin:0">{{ __('panelica_license.client.preparing') }}</p></div>
    @endif
</div>
<script>
document.querySelectorAll('#panelica-license [data-pl-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
        navigator.clipboard.writeText(document.getElementById(button.dataset.plCopy).innerText).then(function () {
            button.textContent = button.dataset.copied;
        });
    });
});
</script>
