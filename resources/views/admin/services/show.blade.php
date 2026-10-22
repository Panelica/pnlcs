@extends('admin.layouts.app')
@section('title', ($service->product?->name ?? 'Service') . ($service->domain ? ' - ' . $service->domain : ''))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>
        {{ $service->product?->name ?? 'Service #'.$service->id }}
        @if($service->domain) &mdash; <span style="font-family:monospace;font-size:18px;">{{ $service->domain }}</span>@endif
        <span class="badge-{{ strtolower($service->status) }}" style="font-size:13px;vertical-align:middle;margin-left:8px;">{{ status_label($service->status) }}</span>
    </h1>
    <a href="{{ route('admin.services.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.services.back') }}</a>
</div>

@if($decision = session('server_decision'))
@php
    $decisionStatus = (string) ($decision['status'] ?? '');
    $decisionDelete = ($decision['action'] ?? '') === 'delete';
    $decisionServer = $service->server?->name ?: ($service->server?->hostname ?: __('admin.services.server_decision_the_server'));
@endphp
{{-- Closing the record while its account is still on the server (#212). --}}
<div class="card" id="server-decision" style="margin-bottom:15px;border:2px solid #d9534f;">
    <div class="card-header" style="background:#fdf2f2;"><strong style="color:#a12a1f;">{{ __('admin.services.server_decision_title', ['server' => $decisionServer]) }}</strong></div>
    <div class="card-body">
        <p style="margin:0 0 6px;font-size:14px;">
            @if(($decision['state'] ?? '') === 'unknown')
                {{ __('admin.services.server_decision_unknown', ['server' => $decisionServer]) }}
            @else
                {{ __('admin.services.server_decision_live', ['account' => $service->username ?: '-', 'domain' => $service->domain ?: '-', 'server' => $decisionServer]) }}
            @endif
        </p>
        <p style="margin:0 0 14px;font-size:13px;color:#666;">{{ $decisionDelete ? __('admin.services.server_decision_delete_why') : __('admin.services.server_decision_status_why', ['status' => status_label($decisionStatus)]) }}</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form method="POST" action="{{ $decisionDelete ? route('admin.services.destroy', $service) : route('admin.services.status', $service) }}">
                @csrf @method($decisionDelete ? 'DELETE' : 'PUT')
                @unless($decisionDelete)<input type="hidden" name="status" value="{{ $decisionStatus }}">@endunless
                <input type="hidden" name="server" value="terminate">
                <button type="submit" class="btn btn-danger btn-sm" autofocus>{{ $decisionDelete ? __('admin.services.decision_terminate_delete') : __('admin.services.decision_terminate_status', ['status' => status_label($decisionStatus)]) }}</button>
            </form>
            <form method="POST" action="{{ $decisionDelete ? route('admin.services.destroy', $service) : route('admin.services.status', $service) }}">
                @csrf @method($decisionDelete ? 'DELETE' : 'PUT')
                @unless($decisionDelete)<input type="hidden" name="status" value="{{ $decisionStatus }}">@endunless
                <input type="hidden" name="server" value="keep">
                <button type="submit" class="btn btn-default btn-sm">{{ $decisionDelete ? __('admin.services.decision_keep_delete') : __('admin.services.decision_keep_status', ['status' => status_label($decisionStatus)]) }}</button>
            </form>
            <a href="{{ route('admin.services.show', $service) }}" class="btn btn-link btn-sm">{{ __('admin.services.decision_cancel') }}</a>
        </div>
    </div>
</div>
@endif

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.services.manage_status') }}</strong></div>
    <div class="card-body">
        <p style="margin:0 0 10px;font-size:12.5px;color:#777;">{{ __('admin.services.manage_status_hint', ['actions' => __('admin.services.module_actions')]) }}</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @php $statusBtns = ['active', 'suspended', 'terminated', 'pending', 'cancelled']; @endphp
            @foreach($statusBtns as $btn)
            <form method="POST" action="{{ route('admin.services.status', $service) }}" style="display:inline-block;text-align:center;" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_status', ['status' => status_label($btn)])))">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $btn }}">
                @if($service->status === $btn)
                <button type="submit" class="btn btn-default btn-sm" disabled>{{ __('admin.services.status_'.$btn) }}</button>
                @else
                <button type="submit" class="btn btn-{{ $btn === 'terminated' ? 'danger' : ($btn === 'suspended' ? 'warning' : ($btn === 'active' ? 'success' : 'info')) }} btn-sm">{{ __('admin.services.status_'.$btn) }}</button>
                @endif
            </form>
            @endforeach
            <span style="width:1px;height:36px;background:#ddd;display:inline-block;margin:0 8px;"></span>
            <form method="POST" action="{{ route('admin.services.destroy', $service) }}" style="display:inline-block;text-align:center;" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_delete')))">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.services.delete') }}</button>
            </form>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:15px;">

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.service_info') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">{{ __('admin.services.product') }}</td><td style="padding:5px 0;font-weight:600;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.domain') }}</td><td style="padding:5px 0;font-family:monospace;font-size:12px;">{{ $service->domain ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.username') }}</td><td style="padding:5px 0;font-family:monospace;">{{ $service->username ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.client') }}</td><td style="padding:5px 0;"><a href="{{ route('admin.clients.show', $service->client_id) }}" style="color:#337ab7;">{{ $service->client->full_name ?? 'N/A' }}</a></td></tr>
                @if($service->order_id)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.order') }}</td><td style="padding:5px 0;"><a href="{{ route('admin.orders.show', $service->order_id) }}" style="color:#337ab7;">#{{ $service->order_id }}</a></td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.server_module') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:40%;">{{ __('admin.services.server') }}</td><td style="padding:5px 0;font-weight:600;">{{ $service->server->name ?? __('admin.services.none_assigned') }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.module') }}</td><td style="padding:5px 0;">{{ $service->product?->server_type ?: ($service->product?->ssl_module ?: __('admin.services.none_label')) }}</td></tr>
                @if($service->suspension_date)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.suspended') }}</td><td style="padding:5px 0;color:#d9534f;">{{ $service->suspension_date->format(date_fmt()) }}</td></tr>
                @endif
                @if($service->suspension_reason)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.reason') }}</td><td style="padding:5px 0;color:#777;">{{ $service->suspension_reason }}</td></tr>
                @endif
                @if($service->termination_date)
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.terminated') }}</td><td style="padding:5px 0;color:#d9534f;">{{ $service->termination_date->format(date_fmt()) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading panel-primary">{{ __('admin.services.billing') }}</div>
        <div class="panel-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#777;width:45%;">{{ __('admin.services.amount') }}</td><td style="padding:5px 0;font-weight:700;font-size:15px;">{{ money_fmt($service->amount) }}<span style="font-size:11px;font-weight:400;color:#999;">/{{ $service->billing_cycle }}</span></td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.first_payment') }}</td><td style="padding:5px 0;">{{ money_fmt($service->first_payment_amount) }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.registered') }}</td><td style="padding:5px 0;">{{ $service->registration_date?->format(date_fmt()) ?? '-' }}</td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.next_due') }}</td><td style="padding:5px 0;">
                    <form method="POST" action="{{ route('admin.services.next-due', $service) }}" style="display:flex;gap:4px;align-items:center;">
                        @csrf @method('PUT')
                        <input type="date" name="next_due_date" value="{{ $service->next_due_date?->format('Y-m-d') ?? '' }}" class="form-control" style="width:145px;font-size:12px;padding:2px 6px;{{ $service->next_due_date?->isPast() ? 'border-color:#d9534f;' : '' }}" {{ $service->status === 'terminated' ? 'disabled' : '' }}>
                        @if($service->status !== 'terminated')
                        <button type="submit" class="btn btn-primary btn-xs">{{ __('admin.services.save') }}</button>
                        @endif
                    </form>
                </td></tr>
                <tr><td style="padding:5px 0;color:#777;">{{ __('admin.services.payment') }}</td><td style="padding:5px 0;">{{ $service->payment_method ? payment_method_label((string) $service->payment_method) : '-' }}</td></tr>
            </table>
        </div>
    </div>
</div>

{{-- Edit the service's record. --}}
<details class="card" style="margin-bottom:15px;" @if($errors->any()) open @endif>
    <summary class="card-header" style="cursor:pointer;"><strong>{{ __('admin.services.edit_service') }}</strong></summary>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.services.update', $service) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;font-size:13px;">
                <label>{{ __('admin.services.product') }}
                    <select name="product_id" class="form-control">
                        @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected((int) old('product_id', $service->product_id) === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <small style="color:#777;">{{ __('admin.services.product_hint') }}</small></label>
                <label>{{ __('admin.services.domain') }}
                    <input type="text" name="domain" value="{{ old('domain', $service->domain) }}" class="form-control"></label>
                <label>{{ __('admin.services.username') }}
                    <input type="text" name="username" value="{{ old('username', $service->username) }}" class="form-control"></label>
                <label>{{ __('admin.services.billing_cycle') }}
                    <select name="billing_cycle" class="form-control">
                        @foreach($cycles as $cycle)
                        <option value="{{ $cycle }}" @selected(old('billing_cycle', $service->billing_cycle) === $cycle)>{{ $cycle }}</option>
                        @endforeach
                    </select></label>
                <label>{{ __('admin.services.recurring_amount') }}
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $service->amount) }}" class="form-control"></label>
                <label>{{ __('admin.services.no_suspend_until') }}
                    <input type="date" name="override_auto_suspend_date" value="{{ old('override_auto_suspend_date', $service->override_auto_suspend_date ? \Illuminate\Support\Carbon::parse($service->override_auto_suspend_date)->format('Y-m-d') : '') }}" class="form-control">
                    <small style="color:#777;">{{ __('admin.services.no_suspend_hint') }}</small></label>
            </div>
            <label style="display:block;margin-top:12px;font-size:13px;">{{ __('admin.services.notes') }}
                <textarea name="notes" rows="3" class="form-control">{{ old('notes', $service->notes) }}</textarea></label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">{{ __('admin.services.save') }}</button>
        </form>
    </div>
</details>

@if($service->disk_limit > 0 || $service->bw_limit > 0 || $service->disk_usage > 0 || $service->bw_usage > 0)
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.services.resource_usage') }}</strong></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        @php $diskPct = $service->disk_limit > 0 ? min(100, ($service->disk_usage / $service->disk_limit) * 100) : 0; @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                <span style="color:#777;">{{ __('admin.services.disk_usage') }}</span>
                <span style="font-weight:600;">{{ mb_fmt($service->disk_usage) }} / {{ $service->disk_limit > 0 ? mb_fmt($service->disk_limit) : '∞' }}</span>
            </div>
            <div style="background:#e9e9e9;border-radius:3px;height:12px;">
                <div style="height:12px;border-radius:3px;background:{{ $diskPct > 85 ? '#d9534f' : '#337ab7' }};width:{{ $diskPct }}%;"></div>
            </div>
        </div>
        @php $bwPct = $service->bw_limit > 0 ? min(100, ($service->bw_usage / $service->bw_limit) * 100) : 0; @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                <span style="color:#777;">{{ __('admin.services.bandwidth') }}</span>
                <span style="font-weight:600;">{{ mb_fmt($service->bw_usage) }} / {{ $service->bw_limit > 0 ? mb_fmt($service->bw_limit) : '∞' }}</span>
            </div>
            <div style="background:#e9e9e9;border-radius:3px;height:12px;">
                <div style="height:12px;border-radius:3px;background:{{ $bwPct > 85 ? '#d9534f' : '#5cb85c' }};width:{{ $bwPct }}%;"></div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.services.module_actions') }}</strong></div>
    <div class="card-body">
        @if(!$service->product?->server_type && !$service->product?->ssl_module)
        <p style="font-size:13px;color:#999;">{{ __('admin.services.no_module') }}</p>
        @elseif($service->product?->ssl_module)
        <p style="font-size:13px;margin-bottom:8px;">{{ __('admin.products.ssl_module') }}: <span style="font-family:monospace;">{{ $service->product->ssl_module }}</span></p>
        @if($service->sslOrder)
            <a href="{{ route('admin.ssl.show', $service->sslOrder) }}" class="btn btn-default btn-sm">{{ __('admin.ssl.order_details') }}</a>
        @else
            <p style="font-size:13px;color:#999;">{{ __('admin.services.no_ssl_order') }}</p>
        @endif
        @else
        @if($pendingSetup)
        {{-- Why the setup has not gone through, which used to show nowhere (#212). --}}
        <div style="margin-bottom:12px;padding:10px 12px;border-radius:5px;background:#fff4e0;border:1px solid #f0d49b;font-size:13px;color:#7a4b00;">
            <strong>{{ __('admin.services.setup_pending_title') }}</strong>
            <span style="display:block;margin-top:3px;font-family:monospace;font-size:12.5px;word-break:break-word;">{{ $pendingSetup->last_error ?: '-' }}</span>
            <span style="display:block;margin-top:3px;">
                @if($pendingSetup->status === 'failed')
                    {{ __('admin.services.setup_gave_up', ['button' => __('admin.services.create_account')]) }}
                @elseif($pendingSetup->next_attempt_at)
                    {{ __('admin.services.setup_next_try', ['time' => $pendingSetup->next_attempt_at->timezone(config('app.timezone'))->format(date_fmt().' H:i'), 'button' => __('admin.services.create_account')]) }}
                @endif
            </span>
        </div>
        @endif
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            {{-- Only while there is something to create. An active service with
                 an account already provisioned made this button a trap: pressing
                 it again just produced "domain already exists" errors. --}}
            {{-- A module that knows (isProvisioned) answers for itself: a
                 license has no username, so the old test kept offering Create
                 on one that was already bought. --}}
            @unless(($moduleScreen['provisioned'] ?? null) ?? (strtolower((string) $service->status) === 'active' && (string) $service->username !== ''))
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'create']) }}" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_create')))">
                @csrf <button type="submit" class="btn btn-success btn-sm">{{ __('admin.services.create_account') }}</button>
            </form>
            @endunless
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'suspend']) }}" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_suspend')))">
                @csrf <button type="submit" class="btn btn-warning btn-sm">{{ __('admin.services.suspend') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'unsuspend']) }}" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_unsuspend')))">
                @csrf <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.services.unsuspend') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'terminate']) }}" onsubmit="return pnConfirm(event, @js(__('admin.services.confirm_terminate')))">
                @csrf <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.services.terminate') }}</button>
            </form>
            @if($moduleScreen['change_password'] ?? true)
            <form method="POST" action="{{ route('admin.services.module-action', [$service, 'changepassword']) }}" style="display:flex;gap:4px;">
                @csrf
                <input type="password" name="password" placeholder="{{ __('admin.services.new_password') }}" required minlength="6" class="form-control" style="width:150px;font-size:13px;">
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.services.change_password') }}</button>
            </form>
            @endif
            @foreach(($moduleScreen['buttons'] ?? []) as $function => $label)
            <form method="POST" action="{{ route('admin.services.module-action', [$service, $function]) }}">
                @csrf <button type="submit" class="btn btn-default btn-sm">{{ $label }}</button>
            </form>
            @endforeach
        </div>
        <p style="font-size:11px;color:#999;">{{ __('admin.services.module_label') }}: <span style="font-family:monospace;">{{ $service->product?->server_type }}</span></p>
        @endif
    </div>
</div>

@if(! empty($moduleScreen['panel']))
<div style="margin-bottom:15px;">{{ $moduleScreen['panel'] }}</div>
@endif

@if(strtolower((string) ($service->server?->type ?? $service->product?->server_type)) === 'proxmox')
@php $pveData = $service->module_data ?? []; @endphp
<div style="margin-bottom:15px;" id="pve-admin">
    <div style="display:flex;justify-content:space-between;align-items:center;margin:4px 0 10px;">
        <strong style="font-size:15px;">{{ __('proxmox.admin.vm_title') }}</strong>
        @if(empty($pveData['proxmox_vmid']))<span style="font-size:12px;color:#666;">{{ __('proxmox.admin.no_vm') }}</span>@endif
    </div>
    @if(!empty($pveData['proxmox_vmid']))
    @php
        $vpsUrls = [];
        foreach (['status', 'graphs', 'power', 'password', 'reinstall', 'snapshots', 'backups'] as $name) {
            $vpsUrls[$name] = route('admin.services.vps.'.$name, $service);
        }
        $vpsUrls['snapshotAction'] = route('admin.services.vps.snapshots.action', $service);
        $vpsUrls['backupAction'] = route('admin.services.vps.backups.action', $service);
        $vpsCanChange = in_array(strtolower((string) $service->status), ['active', 'suspended'], true);
    @endphp
    <x-vps-panel :service="$service" :admin="true" :features="$vpsFeatures" :choices="$reinstallChoices" :can-change="$vpsCanChange" :urls="$vpsUrls"
    >
        <div class="vps-sec">
            @include('admin.services.partials.pve-claim')
        </div>
    </x-vps-panel>
    @else
    <div class="card"><div class="card-body">@include('admin.services.partials.pve-claim')</div></div>
    @endif
</div>
@endif

@if(($productFields ?? collect())->isNotEmpty())
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.products.fields_title') }}</strong></div>
    <form method="POST" action="{{ route('admin.services.fields', $service) }}">
        @csrf @method('PUT')
        <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            @foreach($productFields as $field)
            @php($value = old("custom_fields.{$field->id}", $field->valueFor($service->id)))
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="pf-{{ $field->id }}">{{ $field->field_name }}@if($field->admin_only) <span style="font-weight:400;color:#999;">({{ __('admin.products.fields_admin_only') }})</span>@endif</label>
                @if($field->field_type === 'select')
                <select id="pf-{{ $field->id }}" name="custom_fields[{{ $field->id }}]" class="form-control">
                    <option value="">—</option>
                    @foreach($field->options() as $opt)<option value="{{ $opt }}" @selected($value === $opt)>{{ $opt }}</option>@endforeach
                </select>
                @elseif($field->field_type === 'textarea')
                <textarea id="pf-{{ $field->id }}" name="custom_fields[{{ $field->id }}]" rows="2" class="form-control">{{ $value }}</textarea>
                @elseif($field->field_type === 'checkbox')
                <label style="font-weight:400;display:flex;gap:6px;align-items:center;"><input type="checkbox" id="pf-{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="1" @checked($value)> {{ __('common.yes') }}</label>
                @else
                <input type="{{ in_array($field->field_type, ['number', 'date'], true) ? $field->field_type : 'text' }}" id="pf-{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control">
                @endif
                @error("custom_fields.{$field->id}")<div style="color:#a94442;font-size:12px;">{{ $message }}</div>@enderror
            </div>
            @endforeach
        </div>
        <div style="padding:10px 15px;border-top:1px solid #eee;text-align:right;"><button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button></div>
    </form>
</div>
@endif

@if($service->notes)
<div class="card">
    <div class="card-header"><strong>{{ __('admin.services.notes') }}</strong></div>
    <div class="card-body" style="font-size:13px;white-space:pre-wrap;color:#555;">{{ $service->notes }}</div>
</div>
@endif

<div class="card">
    <div class="card-header"><strong>{{ __('admin.addons.title') }}</strong></div>
    <div class="card-body">
        @if($service->addons->isNotEmpty())
        <table class="data-table" style="margin-bottom:16px;">
            <thead><tr>
                <th>{{ __('common.table.name') }}</th>
                <th>{{ __('common.table.amount') }}</th>
                <th>{{ __('common.table.billing_cycle') }}</th>
                <th>{{ __('client.services.next_due_date') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th style="text-align:right;">{{ __('common.table.actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($service->addons as $addon)
            <tr>
                <td>{{ $addon->label() }}</td>
                <td>{{ number_format((float) $addon->amount, 2) }}</td>
                <td style="text-transform:capitalize;">{{ $addon->billing_cycle }}</td>
                <td>{{ $addon->next_due_date?->format(date_fmt()) ?? '-' }}</td>
                <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ status_label($addon->status) }}</span></td>
                <td style="text-align:right;">
                    @if(in_array(strtolower($addon->status), ['active', 'pending'], true))
                    <form method="POST" action="{{ route('admin.services.addons.cancel', [$service, $addon]) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-xs">{{ __('client.services.addon_cancel') }}</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if(($availableAddons ?? collect())->isNotEmpty())
        <form method="POST" action="{{ route('admin.services.addons.store', $service) }}" style="display:flex;gap:8px;align-items:center;">
            @csrf
            <select name="addon_id" class="form-control" style="max-width:320px;">
                @foreach($availableAddons as $available)
                    <option value="{{ $available->id }}">{{ $available->name }} ({{ number_format($available->priceFor($service->billing_cycle ?: 'Monthly'), 2) }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('client.services.addon_order') }}</button>
        </form>
        @else
            <p style="color:#999;margin:0;">{{ __('admin.addons.no_addons') }}</p>
        @endif
    </div>
</div>

@endsection
