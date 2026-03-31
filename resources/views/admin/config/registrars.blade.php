@extends('admin.layouts.app')
@section('title', 'Domain Registrars')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Domain Registrars</h1>
</div>
<div class="card">
    @if(($registrars ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No domain registrars configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Registrar</th><th>Display Name</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($registrars as $reg)
        <tr>
            <td style="font-family:monospace;">{{ $reg->registrar_name }}</td>
            <td style="font-weight:600;">{{ $reg->description ?? $reg->registrar_name }}</td>
            <td><span class="badge-{{ $reg->disabled ? 'suspended' : 'active' }}">{{ $reg->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('reg-settings-{{ $loop->index }}')" class="btn btn-default btn-xs">Configure</button>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@foreach($registrars ?? [] as $reg)
<x-modal :name="'reg-settings-' . $loop->index" :title="'Configure: ' . $reg->registrar_name" maxWidth="md">
    <form method="POST" action="{{ route('admin.config.registrars.settings.update', $reg->registrar_name) }}">
        @csrf
        <p style="font-size:13px;color:#777;margin-bottom:15px;">Enter settings for the <strong>{{ $reg->registrar_name }}</strong> registrar.</p>
        @php $settings = $reg->settings ?? []; @endphp
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
            <button type="button" onclick="closeModal('reg-settings-{{ $loop->index }}')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
        </div>
    </form>
</x-modal>
@endforeach
@endsection
