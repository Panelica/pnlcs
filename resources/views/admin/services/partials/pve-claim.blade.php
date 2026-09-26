{{-- Link a guest that already exists on Proxmox to this service. --}}
<form method="POST" action="{{ route('admin.services.module-action', [$service, 'pve_claim']) }}" onsubmit="return confirm(@js(__('proxmox.admin.claim_confirm')))">
    @csrf
    <div style="font-weight:700;font-size:13px;margin-bottom:4px;">{{ __('proxmox.admin.claim_title') }}</div>
    <div style="font-size:12px;color:#888;margin-bottom:8px;">{{ __('proxmox.admin.claim_hint') }}</div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="number" name="vmid" min="100" value="{{ ($service->module_data ?? [])['proxmox_vmid'] ?? '' }}" placeholder="{{ __('proxmox.admin.claim_placeholder') }}" aria-label="{{ __('proxmox.admin.claim_placeholder') }}" class="form-control" style="width:160px;max-width:160px;flex:none;" required>
        <button type="submit" class="btn btn-default btn-sm">{{ __('proxmox.admin.claim') }}</button>
    </div>
</form>
