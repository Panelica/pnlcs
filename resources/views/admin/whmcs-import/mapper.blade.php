@extends('admin.layouts.app')
@section('title', __('whmcs_import.title'))
@section('content')
@include('admin.whmcs-import._styles')
@php
    $fieldLabel = fn (string $field) => str_starts_with($field, 'custom_field:') ? substr($field, 13).' ('.__('whmcs_import.custom_field').')' : $field;
    $mappedCount = collect($sourceColumns)->filter(fn ($c) => ($selected[$c['name']] ?? '__skip__') !== '__skip__')->count();
    $totalColumns = count($sourceColumns);
    $types = [
        'clients' => ['icon' => 'fa-users', 'table' => $prefix.'clients', 'label' => __('whmcs_import.target_clients')],
        'domains' => ['icon' => 'fa-globe', 'table' => $prefix.'domains', 'label' => __('whmcs_import.target_domains')],
        'services' => ['icon' => 'fa-server', 'table' => $prefix.'hosting', 'label' => __('whmcs_import.target_services')],
    ];
    $filledConstants = collect($mapping['constants'] ?? [])->filter(fn ($v) => is_string($v) && $v !== '')->count();
    $filledTransforms = collect($mapping['transforms'] ?? [])->filter(fn ($t) => ! empty($t['pattern']))->count();
@endphp

<div class="wi">
    <div class="wi-hero">
        <div class="wi-hero__row">
            <div class="wi-hero__icon"><i class="fas fa-shuffle"></i></div>
            <div class="wi-hero__body">
                <h1>{{ __('whmcs_import.field_mapping') }}</h1>
                <p>{{ $connection->name ?: $connection->database }} · {{ $connection->host }}:{{ $connection->port }} / {{ $connection->database }}</p>
                <div class="wi-chips">
                    <span class="wi-chip"><i class="fas fa-table"></i> {{ __('whmcs_import.total_rows', ['count' => number_format($totalCount)]) }}</span>
                    <span class="wi-chip"><i class="fas fa-lock"></i> {{ __('whmcs_import.badge_read_only') }}</span>
                </div>
            </div>
            <div class="wi-hero__back"><a href="{{ route('admin.whmcs-import.index') }}"><i class="fas fa-arrow-left"></i> {{ __('whmcs_import.saved_connections') }}</a></div>
        </div>
    </div>

    @include('admin.whmcs-import._steps', ['current' => 2])

    <nav class="wi-tabs" aria-label="{{ __('whmcs_import.import_type') }}">
        @foreach($types as $key => $type)
            <a href="{{ route('admin.whmcs-import.mapper', $connection) }}?table={{ urlencode($type['table']) }}" class="wi-tab {{ $target === $key ? 'wi-tab--active' : '' }}" @if($target === $key) aria-current="page" @endif>
                <i class="fas {{ $type['icon'] }}"></i> {{ $type['label'] }}
            </a>
        @endforeach
    </nav>

    @if($target === 'domains')
        <div class="wi-note"><i class="fas fa-circle-info" style="margin-top:2px;"></i><span>{{ __('whmcs_import.domains_client_hint') }}</span></div>
    @endif

    @if(!empty($errors))
        <div class="wi-problems" role="alert">
            <h2><i class="fas fa-circle-exclamation"></i> {{ __('whmcs_import.validation_title') }}</h2>
            <ul>
                @foreach($errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.whmcs-import.preview', $connection) }}">
        @csrf

        <div class="wi-card">
            <div class="wi-card__head">
                <span class="wi-card__icon"><i class="fas fa-sliders"></i></span>
                <h2>{{ __('whmcs_import.import_mode') }}</h2>
            </div>
            <div class="wi-card__body">
                <div class="wi-fields" style="margin-bottom:0;">
                    <div class="wi-field">
                        <label for="wi-source">{{ __('whmcs_import.source_table') }}</label>
                        <select id="wi-source" name="source_table" onchange="this.form.action='{{ route('admin.whmcs-import.mapper', $connection) }}';this.form.method='GET';this.form.submit()">
                            @foreach($tables as $table)
                                <option value="{{ $table }}" @selected($table === $sourceTable)>{{ $table }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wi-field">
                        <label for="wi-mode">{{ __('whmcs_import.import_mode') }}</label>
                        <select id="wi-mode" name="import_mode">
                            @foreach(array_keys(__('whmcs_import.modes')) as $mode)
                                <option value="{{ $mode }}" @selected($mode === $importMode)>{{ __('whmcs_import.modes.'.$mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wi-field">
                        <label for="wi-match">{{ __('whmcs_import.match_key') }}</label>
                        <select id="wi-match" name="match_key">
                            <option value="">—</option>
                            @foreach($targetFields as $field)
                                <option value="{{ $field }}" @selected($field === $matchKey)>{{ $fieldLabel($field) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="wi-card">
            <div class="wi-card__head">
                <span class="wi-card__icon"><i class="fas fa-diagram-project"></i></span>
                <h2>{{ __('whmcs_import.field_mapping') }}</h2>
                <div class="wi-card__aside wi-meter">
                    <span>{{ __('whmcs_import.mapped_count', ['count' => $mappedCount, 'total' => $totalColumns]) }}</span>
                    <span class="wi-meter__bar"><span class="wi-meter__fill" style="width:{{ $totalColumns ? round($mappedCount / $totalColumns * 100) : 0 }}%"></span></span>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="wi-table wi-map">
                    <thead><tr>
                        <th style="width:42%;">{{ __('whmcs_import.source_column') }}</th>
                        <th style="width:48px;"></th>
                        <th>{{ __('whmcs_import.target_field') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($sourceColumns as $column)
                        @php
                            $name = $column['name'];
                            $suggested = $suggestions[$name] ?? null;
                            $current = $selected[$name] ?? ($suggested ?? '__skip__');
                        @endphp
                        <tr class="{{ $current === '__skip__' ? 'is-skipped' : 'is-mapped' }}">
                            <td><span class="wi-tag">{{ $name }}</span><span class="wi-type">{{ $column['type'] }}</span></td>
                            <td class="wi-arrow"><i class="fas fa-arrow-right"></i></td>
                            <td>
                                <select name="mapping[{{ $name }}]" aria-label="{{ $name }}" onchange="this.closest('tr').className = this.value === '__skip__' ? 'is-skipped' : 'is-mapped'">
                                    <option value="__skip__" @selected($current === '__skip__')>{{ __('whmcs_import.skip') }}</option>
                                    @foreach($targetFields as $field)
                                        <option value="{{ $field }}" @selected($current === $field)>{{ $fieldLabel($field) }}{{ $suggested === $field && $current !== $field ? ' ('.__('whmcs_import.suggested').')' : '' }}</option>
                                    @endforeach
                                </select>
                                @if($suggested !== null && $current === $suggested)
                                    <div class="wi-badge-suggest"><i class="fas fa-wand-magic-sparkles"></i> {{ __('whmcs_import.suggested') }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <details class="wi-fold" @if($filledConstants) open @endif>
            <summary><span class="wi-card__icon" style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--wi-soft);color:var(--wi-primary);"><i class="fas fa-thumbtack"></i></span>{{ __('whmcs_import.constants') }}@if($filledConstants)<span class="wi-pill wi-pill--blue">{{ $filledConstants }}</span>@endif<i class="fas fa-chevron-down wi-chev"></i></summary>
            <div class="wi-fold__body">
                <div style="overflow-x:auto;">
                    <table class="wi-table">
                        <thead><tr>
                            <th>{{ __('whmcs_import.target_field') }}</th>
                            <th>{{ __('whmcs_import.constant_value') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($targetFields as $field)
                            <tr>
                                <td><span class="wi-tag">{{ $fieldLabel($field) }}</span></td>
                                <td><input type="text" name="constants[{{ $field }}]" value="{{ $mapping['constants'][$field] ?? '' }}" aria-label="{{ $fieldLabel($field) }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="wi-fold" @if($filledTransforms) open @endif>
            <summary><span class="wi-card__icon" style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--wi-soft);color:var(--wi-primary);"><i class="fas fa-code"></i></span>{{ __('whmcs_import.transforms') }}@if($filledTransforms)<span class="wi-pill wi-pill--blue">{{ $filledTransforms }}</span>@endif<i class="fas fa-chevron-down wi-chev"></i></summary>
            <div class="wi-fold__body">
                <p class="wi-fold__hint">{{ __('whmcs_import.transforms_hint') }}</p>
                <div style="overflow-x:auto;">
                    <table class="wi-table">
                        <thead><tr>
                            <th>{{ __('whmcs_import.target_field') }}</th>
                            <th>{{ __('whmcs_import.transform_pattern') }}</th>
                            <th>{{ __('whmcs_import.transform_replacement') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($targetFields as $field)
                            <tr>
                                <td><span class="wi-tag">{{ $fieldLabel($field) }}</span></td>
                                <td><input type="text" name="transforms[{{ $field }}][pattern]" value="{{ $mapping['transforms'][$field]['pattern'] ?? '' }}" placeholder="/[^0-9]/" aria-label="{{ __('whmcs_import.transform_pattern') }}"></td>
                                <td><input type="text" name="transforms[{{ $field }}][replacement]" value="{{ $mapping['transforms'][$field]['replacement'] ?? '' }}" aria-label="{{ __('whmcs_import.transform_replacement') }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        @if($target === 'services' && count($whmcsProducts))
        <details class="wi-fold" open>
            <summary><span class="wi-card__icon" style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--wi-soft);color:var(--wi-primary);"><i class="fas fa-cubes"></i></span>{{ __('whmcs_import.product_mapping') }}<i class="fas fa-chevron-down wi-chev"></i></summary>
            <div class="wi-fold__body">
                <p class="wi-fold__hint">{{ __('whmcs_import.product_mapping_hint') }}</p>
                <div style="overflow-x:auto;">
                    <table class="wi-table">
                        <thead><tr>
                            <th>{{ __('whmcs_import.source_column') }}</th>
                            <th>{{ __('whmcs_import.target_field') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($whmcsProducts as $wp)
                            <tr>
                                <td><span class="wi-tag">{{ $wp['name'] }}</span><span class="wi-type">{{ $wp['type'] }}</span></td>
                                <td>
                                    <select name="product_mapping[{{ $wp['name'] }}]">
                                        <option value="">{{ __('whmcs_import.product_unmatched') }}</option>
                                        @foreach($pnlcsProducts as $p)
                                        <option value="{{ $p->id }}" @selected(($productMap[$wp['name']] ?? null) === $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
        @endif

        <div class="wi-bar">
            <button type="submit" class="btn wi-btn-outline"><i class="fas fa-eye"></i> {{ __('whmcs_import.preview_button') }}</button>
            <button type="submit" formaction="{{ route('admin.whmcs-import.import', $connection) }}" class="btn btn-primary"><i class="fas fa-file-import"></i> {{ __('whmcs_import.import_button') }}</button>
            <span class="wi-bar__spacer"></span>
            <input type="text" name="profile_name" placeholder="{{ __('whmcs_import.profile_name') }}" aria-label="{{ __('whmcs_import.profile_name') }}">
            <button type="submit" formaction="{{ route('admin.whmcs-import.profile.store', $connection) }}" class="btn btn-sm wi-btn-outline"><i class="fas fa-bookmark"></i> {{ __('whmcs_import.save_profile') }}</button>
        </div>
    </form>

    @if($preview !== null)
        <div class="wi-card" id="preview">
            <div class="wi-card__head">
                <span class="wi-card__icon"><i class="fas fa-eye"></i></span>
                <h2>{{ __('whmcs_import.preview_title', ['count' => count($preview)]) }}</h2>
            </div>
            @if(count($preview) === 0)
                <div class="wi-empty"><i class="fas fa-inbox"></i>{{ __('whmcs_import.no_preview_rows') }}</div>
            @else
                @foreach($preview as $i => $record)
                    @php
                        $person = trim(($record['target']['first_name'] ?? '').' '.($record['target']['last_name'] ?? ''));
                        $heading = $person ?: ($record['target']['domain'] ?? null) ?: ($record['target']['email'] ?? ($record['source']['email'] ?? '')) ?: '#'.($i + 1);
                    @endphp
                    <div class="wi-record">
                        <div class="wi-record__title"><span class="wi-record__num">{{ $i + 1 }}</span>{{ $heading }}</div>
                        <div class="wi-pair">
                            <div class="wi-pair__side wi-pair__side--src">
                                <div class="wi-pair__label">{{ __('whmcs_import.preview_source') }}</div>
                                @foreach($mapping['columns'] as $src => $tgt)
                                    <div class="wi-kv"><b>{{ $src }}</b><span>{{ $record['source'][$src] ?? '' }}</span></div>
                                @endforeach
                            </div>
                            <div class="wi-pair__mid"><i class="fas fa-arrow-right"></i></div>
                            <div class="wi-pair__side wi-pair__side--dst">
                                <div class="wi-pair__label">{{ __('whmcs_import.preview_target') }}</div>
                                @foreach($record['target'] as $field => $value)
                                    <div class="wi-kv"><b>{{ $fieldLabel($field) }}</b><span>{{ $value }}</span></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</div>
@endsection
