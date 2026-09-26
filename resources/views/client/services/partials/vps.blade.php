{{-- Virtual server controls (Proxmox): the shared VPS panel with the
     customer's own routes. --}}
@php
    $vpsUrls = [];
    foreach (['status', 'graphs', 'power', 'password', 'reinstall', 'snapshots', 'backups'] as $name) {
        $vpsUrls[$name] = route('client.services.vps.'.$name, $service);
    }
    $vpsUrls['snapshotAction'] = route('client.services.vps.snapshots.action', $service);
    $vpsUrls['backupAction'] = route('client.services.vps.backups.action', $service);
    $vpsCanChange = strtolower((string) $service->status) === 'active';
@endphp
<div class="sv-sec"><i class="ri-server-line"></i>{{ __('proxmox.client.title') }}</div>
<x-vps-panel :service="$service" :features="$vpsFeatures" :choices="$reinstallChoices" :can-change="$vpsCanChange" :urls="$vpsUrls" />
