@extends('admin.layouts.app')
@section('title', __('admin.gateways.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.gateways.title') }}</h1>
</div>

<p style="color:#666;font-size:13px;margin-bottom:15px;">{{ __('admin.gateways.description') }}</p>

{{-- Each gateway asks for the settings its own module reads. The list used to
     be written out here by hand, with field names that did not match, so
     PayPal was asked for an email address it never reads and Authorize.net for
     an api_login it does not know. --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:15px;">
@foreach($gateways as $gw)
    <details class="card gw-card" style="overflow:hidden;">
        <summary style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px 16px;list-style:none;">
            <span class="gw-chevron" style="display:inline-block;transition:transform .2s;color:#999;">&#9656;</span>
            <span style="flex:1;font-weight:600;">{{ $gw->label }}</span>
            <span class="badge {{ $gw->active ? 'badge-active' : 'badge-cancelled' }}">{{ $gw->active ? __('common.status.active') : __('common.status.inactive') }}</span>
        </summary>
        <div style="padding:0 16px 16px;border-top:1px solid #e5e7eb;">
            <form method="POST" action="{{ route('admin.config.gateways.settings.update', $gw->name) }}">
                @csrf
                <div class="form-group">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="active" value="1" {{ $gw->active ? 'checked' : '' }}> {{ __('admin.gateways.enable_gateway') }}
                    </label>
                </div>

                @forelse($gw->fields as $field)
                    @php
                        $key = $field['name'];
                        $value = $gw->values[$key] ?? ($field['default'] ?? '');
                        $type = $field['type'] ?? 'text';
                    @endphp
                    <div class="form-group">
                        <label class="form-label">{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $key)) }}</label>
                        @if($type === 'password')
                            <input type="password" name="settings[{{ $key }}]" value="{{ $value }}" class="form-control" autocomplete="new-password">
                        @elseif($type === 'textarea')
                            <textarea name="settings[{{ $key }}]" rows="3" class="form-control">{{ $value }}</textarea>
                        @elseif($type === 'yesno')
                            <select name="settings[{{ $key }}]" class="form-control">
                                <option value="0" {{ (string) $value === '0' ? 'selected' : '' }}>{{ __('common.no') }}</option>
                                <option value="1" {{ (string) $value === '1' ? 'selected' : '' }}>{{ __('common.yes') }}</option>
                            </select>
                        @elseif($type === 'select')
                            <select name="settings[{{ $key }}]" class="form-control">
                                @foreach(($field['options'] ?? []) as $optValue => $optLabel)
                                <option value="{{ $optValue }}" {{ (string) $value === (string) $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="settings[{{ $key }}]" value="{{ $value }}" class="form-control">
                        @endif
                    </div>
                @empty
                    <div class="form-group">
                        <label class="form-label">{{ __('admin.registrars.config_json') }}</label>
                        <textarea name="settings_json" rows="4" class="form-control" placeholder='{"key":"value"}'></textarea>
                    </div>
                @endforelse

                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">{{ __('admin.gateways.save_settings') }}</button>
            </form>
        </div>
    </details>
@endforeach
</div>

<style>
    .gw-card summary::-webkit-details-marker { display: none; }
    .gw-card[open] .gw-chevron { transform: rotate(90deg); }
</style>
@endsection
