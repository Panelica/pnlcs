@extends('admin.layouts.app')
@section('title', __('whmcs_import.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('whmcs_import.title') }}</h1>
    <a href="{{ route('admin.whmcs-import.index') }}" class="btn btn-secondary btn-sm">&larr; {{ __('whmcs_import.saved_connections') }}</a>
</div>

<div style="color:#666;font-size:13px;margin-bottom:15px;">
    {{ $connection->host }}:{{ $connection->port }} / {{ $connection->database }}
    &middot; {{ __('whmcs_import.total_rows', ['count' => $totalCount]) }}
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(!empty($errors))
    <div class="card" style="margin-bottom:15px;border-color:#fca5a5;">
        <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#b91c1c;">{{ __('whmcs_import.validation_title') }}</div>
        <ul style="padding:16px;margin:0;color:#b91c1c;">
            @foreach($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.whmcs-import.preview', $connection) }}">
    @csrf

    <div class="card" style="margin-bottom:15px;">
        <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.connection_title') }}</div>
        <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.source_table') }}</label>
                <select name="source_table" class="form-control" onchange="this.form.action='{{ route('admin.whmcs-import.mapper', $connection) }}';this.form.method='GET';this.form.submit()">
                    @foreach($tables as $table)
                        <option value="{{ $table }}" @selected($table === $sourceTable)>{{ $table }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.match_key') }}</label>
                <select name="match_key" class="form-control">
                    <option value="">—</option>
                    @foreach($targetFields as $field)
                        <option value="{{ $field }}" @selected($field === $matchKey)>{{ $field }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.import_mode') }}</label>
                <select name="import_mode" class="form-control">
                    @foreach(array_keys(__('whmcs_import.modes')) as $mode)
                        <option value="{{ $mode }}" @selected($mode === $importMode)>{{ __('whmcs_import.modes.'.$mode) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.field_mapping') }}</div>
        <table class="data-table">
            <thead><tr>
                <th>{{ __('whmcs_import.source_column') }}</th>
                <th style="width:60px;"></th>
                <th>{{ __('whmcs_import.target_field') }}</th>
            </tr></thead>
            <tbody>
            @foreach($sourceColumns as $column)
                @php
                    $name = $column['name'];
                    $suggested = $suggestions[$name] ?? null;
                    $current = $selected[$name] ?? ($suggested ?? '__skip__');
                @endphp
                <tr>
                    <td>
                        <code>{{ $name }}</code>
                        <span style="color:#999;font-size:12px;">{{ $column['type'] }}</span>
                    </td>
                    <td style="text-align:center;color:#999;">&#8595;</td>
                    <td>
                        <select name="mapping[{{ $name }}]" class="form-control">
                            <option value="__skip__" @selected($current === '__skip__')>{{ __('whmcs_import.skip') }}</option>
                            @foreach($targetFields as $field)
                                @php $fieldLabel = str_starts_with($field, 'custom_field:') ? substr($field, 13).' ('.__('whmcs_import.custom_field').')' : $field; @endphp
                                <option value="{{ $field }}" @selected($current === $field)>
                                    {{ $fieldLabel }}{{ $suggested === $field ? ' (' . __('whmcs_import.suggested') . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <details class="card" style="margin-bottom:15px;overflow:hidden;">
        <summary style="cursor:pointer;padding:16px;font-weight:600;list-style:none;">{{ __('whmcs_import.constants') }}</summary>
        <div style="padding:0 16px 16px;">
            <p style="color:#666;font-size:12px;">{{ __('whmcs_import.constant_value') }} — {{ __('whmcs_import.skip') }}</p>
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('whmcs_import.target_field') }}</th>
                    <th>{{ __('whmcs_import.constant_value') }}</th>
                </tr></thead>
                <tbody>
                @foreach($targetFields as $field)
                    <tr>
                        <td><code>{{ str_starts_with($field, 'custom_field:') ? substr($field, 13).' ('.__('whmcs_import.custom_field').')' : $field }}</code></td>
                        <td><input type="text" name="constants[{{ $field }}]" value="{{ $mapping['constants'][$field] ?? '' }}" class="form-control"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <div style="display:flex;gap:10px;align-items:center;margin-bottom:15px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-secondary">{{ __('whmcs_import.preview_button') }}</button>
        <button type="submit" formaction="{{ route('admin.whmcs-import.import', $connection) }}" class="btn btn-primary">{{ __('whmcs_import.import_button') }}</button>
        <div style="flex:1;"></div>
        <input type="text" name="profile_name" class="form-control" style="width:200px;" placeholder="{{ __('whmcs_import.profile_name') }}">
        <button type="submit" formaction="{{ route('admin.whmcs-import.profile.store', $connection) }}" class="btn btn-secondary btn-sm">{{ __('whmcs_import.save_profile') }}</button>
    </div>
</form>

@if($preview !== null)
    <div class="card">
        <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.preview_title', ['count' => count($preview)]) }}</div>
        @if(count($preview) === 0)
            <div style="padding:16px;color:#666;">{{ __('whmcs_import.no_preview_rows') }}</div>
        @else
            <div>
                @foreach($preview as $i => $record)
                    @php
                        $name = trim(($record['target']['first_name'] ?? '').' '.($record['target']['last_name'] ?? ''));
                        $email = $record['target']['email'] ?? ($record['source']['email'] ?? '');
                        $heading = $name ?: $email ?: '#'.($i + 1);
                    @endphp
                    <div style="padding:16px;{{ !$loop->last ? 'border-bottom:2px solid #e5e7eb;' : '' }}">
                        <div style="font-weight:600;margin-bottom:10px;color:#1a4d80;">{{ $heading }}</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:12px;">
                                <div style="font-size:11px;text-transform:uppercase;color:#999;margin-bottom:6px;">WHMCS</div>
                                @foreach($mapping['columns'] as $src => $tgt)
                                    <div style="font-size:13px;line-height:1.6;"><strong>{{ $src }}:</strong> {{ $record['source'][$src] ?? '' }}</div>
                                @endforeach
                            </div>
                            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;">
                                <div style="font-size:11px;text-transform:uppercase;color:#0c4a6e;margin-bottom:6px;">PNLCS</div>
                                @foreach($record['target'] as $field => $value)
                                    @php $tLabel = str_starts_with($field, 'custom_field:') ? substr($field, 13).' ('.__('whmcs_import.custom_field').')' : $field; @endphp
                                    <div style="font-size:13px;line-height:1.6;"><strong>{{ $tLabel }}:</strong> {{ $value }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif

@endsection
