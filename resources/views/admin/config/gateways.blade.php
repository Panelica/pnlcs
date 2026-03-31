@extends('admin.layouts.app')
@section('title', 'Payment Gateways')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Payment Gateways</h1>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

<div class="card">
    @if(($gateways ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No payment gateways configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Gateway Name</th><th>Display Name</th><th>Order</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($gateways as $gw)
        <tr>
            <td style="font-family:monospace;">{{ $gw->gateway_name }}</td>
            <td style="font-weight:600;">{{ $gw->description }}</td>
            <td>{{ $gw->order_num ?? 0 }}</td>
            <td><span class="badge-{{ $gw->disabled ? 'suspended' : 'active' }}">{{ $gw->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('gw-settings-{{ $loop->index }}')" class="btn btn-default btn-xs">Configure</button>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@foreach($gateways ?? [] as $gw)
<x-modal :name="'gw-settings-' . $loop->index" :title="'Configure: ' . $gw->gateway_name" maxWidth="md">
    <form method="POST" action="{{ route('admin.config.gateways.settings.update', $gw->gateway_name) }}">
        @csrf
        <p style="font-size:13px;color:#777;margin-bottom:15px;">Enter settings for the <strong>{{ $gw->gateway_name }}</strong> gateway.</p>
        @php $settings = $gw->settings ?? []; @endphp
        @if(!empty($settings))
            @foreach($settings as $key => $val)
            <div class="form-group">
                <label class="form-label" style="text-transform:capitalize;">{{ str_replace('_',' ',$key) }}</label>
                <input type="text" name="settings[{{ $key }}]" value="{{ $val }}" class="form-control">
            </div>
            @endforeach
        @else
            <div class="form-group"><label class="form-label">Configuration (JSON)</label><textarea name="settings_json" rows="4" class="form-control" placeholder='{"key":"value"}'></textarea></div>
        @endif
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('gw-settings-{{ $loop->index }}')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
