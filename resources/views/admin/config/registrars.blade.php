@extends('admin.layouts.app')
@section('title', __('admin.registrars.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.registrars.title') }}</h1>
</div>

@if(($registrars ?? collect())->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.registrars.no_registrars') }}</div></div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:15px;align-items:start;">
@foreach($registrars as $reg)
    <details class="card gw-card" style="overflow:visible;">
        <summary style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px 16px;list-style:none;">
            <span class="gw-chevron" style="display:inline-block;transition:transform .2s;color:#999;">&#9656;</span>
            <span style="flex:1;font-weight:600;display:flex;align-items:center;gap:8px;">
                {{ $reg->label }}
                @if($reg->help)
                <span class="help-badge" tabindex="0">?
                    <span class="help-tooltip"><span class="help-tooltip-inner">{!! $reg->help !!}</span></span>
                </span>
                @endif
            </span>
            <span class="badge {{ $reg->active ? 'badge-active' : 'badge-cancelled' }}">{{ $reg->active ? __('common.status.active') : __('common.status.inactive') }}</span>
        </summary>
        <div style="padding:0 16px 16px;border-top:1px solid #e5e7eb;">
            <form method="POST" action="{{ route('admin.config.registrars.settings.update', $reg->registrar_name) }}">
                @csrf
                <div class="form-group">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="visible" value="1" {{ $reg->active ? 'checked' : '' }}> {{ __('admin.registrars.enable_registrar') }}
                    </label>
                </div>

                @forelse($reg->fields as $field)
                    @php
                        $key = $field['name'];
                        $value = $reg->values[$key] ?? ($field['default'] ?? '');
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

                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">{{ __('admin.registrars.save_settings') }}</button>
            </form>
            @if($reg->testable)
            <form method="POST" action="{{ route('admin.config.registrars.test', $reg->registrar_name) }}" style="margin-top:6px;">
                @csrf
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.registrars.test') }}</button>
            </form>
            @endif
        </div>
    </details>
@endforeach
</div>
@endif

<style>
    .gw-card summary::-webkit-details-marker { display: none; }
    .gw-card[open] .gw-chevron { transform: rotate(90deg); }
    .gw-card { overflow: visible !important; }
    .help-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #cbd5e1;
        color: #334155;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        cursor: help;
        position: relative;
        flex: 0 0 auto;
    }
    .help-tooltip {
        visibility: hidden;
        opacity: 0;
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        padding-top: 8px;
        z-index: 300;
        transition: opacity .12s ease;
    }
    .help-tooltip-inner {
        display: block;
        width: 360px;
        max-width: 80vw;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        box-shadow: 0 6px 20px rgba(0,0,0,.15);
        padding: 12px 14px;
        font-size: 12px;
        font-weight: 400;
        line-height: 1.55;
        color: #374151;
        text-align: left;
    }
    .help-badge:hover .help-tooltip,
    .help-badge:focus .help-tooltip {
        visibility: visible;
        opacity: 1;
    }
</style>
@endsection
