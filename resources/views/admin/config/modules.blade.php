@extends("admin.layouts.app")
@section("title", __("admin.modules.title"))
@section("content")

@php
    // Where each type is configured. The switch lives here; the settings
    // (keys, credentials) stay on the screen that already owns them.
    $configure = [
        'server' => route('admin.config.servers'),
        'gateway' => route('admin.config.gateways'),
        'registrar' => route('admin.config.registrars'),
        'ssl' => route('admin.config.sslModules'),
        'addon' => route('admin.config.addons.modules'),
    ];
@endphp

<div class="page-header">
    <h1>{{ __('admin.modules.title') }}</h1>
    <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ __('admin.modules.description') }}</p>
</div>

@foreach($groups as $type => $rows)
<div class="card" style="margin-bottom:16px;" id="modules-{{ $type }}">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <strong>{{ __('admin.modules.type_'.$type) }}</strong>
        <a href="{{ $configure[$type] }}" class="btn btn-default btn-xs">{{ __('admin.modules.configure') }}</a>
    </div>
    @if($rows->isEmpty())
    <div class="card-body" style="text-align:center;padding:24px;color:var(--pn-muted);">{{ __('admin.modules.none') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('admin.modules.col_module') }}</th>
            <th>{{ __('admin.modules.col_source') }}</th>
            <th>{{ __('common.table.status') }}</th>
            @if(in_array($type, ['server', 'ssl'], true))<th>{{ __('admin.modules.col_usage') }}</th>@endif
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($rows as $row)
        <tr data-module="{{ $type }}/{{ $row->key }}">
            <td><span style="font-weight:600;">{{ $row->label }}</span> <span style="font-family:monospace;font-size:11px;color:var(--pn-muted);">{{ $row->key }}</span></td>
            <td><span class="badge {{ $row->third_party ? 'badge-pending' : 'badge-draft' }}">{{ $row->third_party ? __('admin.modules.source_third_party') : __('admin.modules.source_core') }}</span></td>
            <td><span class="badge {{ $row->active ? 'badge-active' : 'badge-suspended' }}">{{ $row->active ? __('common.status.active') : __('common.status.disabled') }}</span></td>
            @if(in_array($type, ['server', 'ssl'], true))<td>{{ $row->in_use }}</td>@endif
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.modules.toggle', [$type, $row->key]) }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="active" value="{{ $row->active ? '0' : '1' }}">
                    @if($row->active)
                        <button type="submit" class="btn btn-default btn-xs" @if($row->in_use > 0) disabled title="{{ __('admin.modules.in_use_cannot_disable', ['count' => $row->in_use]) }}" @endif>{{ __('admin.modules.switch_off') }}</button>
                    @else
                        <button type="submit" class="btn btn-primary btn-xs">{{ __('admin.modules.switch_on') }}</button>
                    @endif
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endforeach

@endsection
