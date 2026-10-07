@extends('admin.layouts.app')
@section('title', __('whmcs_import.title'))
@section('content')
@include('admin.whmcs-import._styles')

<div class="wi">
    <div class="wi-hero">
        <div class="wi-hero__row">
            <div class="wi-hero__icon"><i class="fas fa-file-import"></i></div>
            <div class="wi-hero__body">
                <h1>{{ __('whmcs_import.title') }}</h1>
                <p>{{ __('whmcs_import.description') }}</p>
                <div class="wi-chips">
                    <span class="wi-chip"><i class="fas fa-lock"></i> {{ __('whmcs_import.badge_read_only') }}</span>
                    <span class="wi-chip"><i class="fas fa-eye"></i> {{ __('whmcs_import.badge_preview') }}</span>
                    <span class="wi-chip"><i class="fas fa-list-check"></i> {{ __('whmcs_import.badge_logged') }}</span>
                </div>
            </div>
        </div>
    </div>

    @include('admin.whmcs-import._steps', ['current' => 1])

    @if($errors->has('connection'))
        <div class="wi-problems" role="alert">
            <h2><i class="fas fa-circle-exclamation"></i> {{ __('whmcs_import.validation_title') }}</h2>
            <div style="font-size:13px;">{{ $errors->first('connection') }}</div>
        </div>
    @endif

    <div class="wi-grid">
        <div class="wi-card">
            <div class="wi-card__head">
                <span class="wi-card__icon"><i class="fas fa-plug"></i></span>
                <h2>{{ __('whmcs_import.connection_title') }}</h2>
            </div>
            <form method="POST" action="{{ route('admin.whmcs-import.connection.store') }}" class="wi-card__body">
                @csrf
                <p class="wi-section-label">{{ __('whmcs_import.section_server') }}</p>
                <div class="wi-fields">
                    <div class="wi-field" style="grid-column:span 2;">
                        <label for="wi-host">{{ __('whmcs_import.host') }}</label>
                        <input id="wi-host" type="text" name="host" value="{{ old('host') }}" required autocomplete="off">
                    </div>
                    <div class="wi-field">
                        <label for="wi-port">{{ __('whmcs_import.port') }}</label>
                        <input id="wi-port" type="number" name="port" value="{{ old('port', 3306) }}" required>
                    </div>
                </div>

                <p class="wi-section-label">{{ __('whmcs_import.section_database') }}</p>
                <div class="wi-fields">
                    <div class="wi-field">
                        <label for="wi-database">{{ __('whmcs_import.database') }}</label>
                        <input id="wi-database" type="text" name="database" value="{{ old('database') }}" required autocomplete="off">
                    </div>
                    <div class="wi-field">
                        <label for="wi-username">{{ __('whmcs_import.username') }}</label>
                        <input id="wi-username" type="text" name="username" value="{{ old('username') }}" required autocomplete="off">
                    </div>
                    <div class="wi-field">
                        <label for="wi-password">{{ __('whmcs_import.password') }}</label>
                        <input id="wi-password" type="password" name="password" value="" autocomplete="new-password">
                    </div>
                    <div class="wi-field">
                        <label for="wi-prefix">{{ __('whmcs_import.prefix') }}</label>
                        <input id="wi-prefix" type="text" name="prefix" value="{{ old('prefix', 'tbl') }}">
                    </div>
                    <div class="wi-field" style="grid-column:span 2;">
                        <label for="wi-name">{{ __('whmcs_import.connection_name') }}</label>
                        <input id="wi-name" type="text" name="name" value="{{ old('name') }}">
                    </div>
                </div>

                <div class="wi-actions">
                    <button type="submit" formaction="{{ route('admin.whmcs-import.connection.test') }}" class="btn wi-btn-outline"><i class="fas fa-plug-circle-check"></i> {{ __('whmcs_import.test_connection') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('whmcs_import.save_and_continue') }} <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>

        <div class="wi-card">
            <div class="wi-card__head">
                <span class="wi-card__icon"><i class="fas fa-database"></i></span>
                <h2>{{ __('whmcs_import.saved_connections') }}</h2>
                @if($connections->isNotEmpty())<span class="wi-card__aside">{{ $connections->count() }}</span>@endif
            </div>
            <div class="wi-card__body">
                @forelse($connections as $conn)
                    <div class="wi-conn">
                        <span class="wi-conn__icon"><i class="fas fa-server"></i></span>
                        <div class="wi-conn__body">
                            <div class="wi-conn__name">{{ $conn->name ?: $conn->database }}</div>
                            <div class="wi-conn__meta">{{ $conn->host }}:{{ $conn->port }} · {{ $conn->database }}</div>
                        </div>
                        <a href="{{ route('admin.whmcs-import.mapper', $conn) }}" class="btn btn-primary btn-sm">{{ __('whmcs_import.open_mapper') }}</a>
                    </div>
                @empty
                    <div class="wi-empty"><i class="fas fa-database"></i>{{ __('whmcs_import.no_connections') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($logs->isNotEmpty())
    <div class="wi-card">
        <div class="wi-card__head">
            <span class="wi-card__icon"><i class="fas fa-clock-rotate-left"></i></span>
            <h2>{{ __('whmcs_import.recent_logs') }}</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="wi-table">
                <thead><tr>
                    <th>{{ __('whmcs_import.log_date') }}</th>
                    <th>{{ __('whmcs_import.source_table') }}</th>
                    <th class="wi-num">{{ __('whmcs_import.log_total') }}</th>
                    <th class="wi-num">{{ __('whmcs_import.log_added') }}</th>
                    <th class="wi-num">{{ __('whmcs_import.log_updated') }}</th>
                    <th class="wi-num">{{ __('whmcs_import.log_errors') }}</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td><span class="wi-tag">{{ $log->source_table }}</span></td>
                        <td class="wi-num"><span class="wi-pill wi-pill--gray">{{ $log->total }}</span></td>
                        <td class="wi-num"><span class="wi-pill {{ $log->added ? 'wi-pill--green' : 'wi-pill--gray' }}">{{ $log->added }}</span></td>
                        <td class="wi-num"><span class="wi-pill {{ $log->updated ? 'wi-pill--blue' : 'wi-pill--gray' }}">{{ $log->updated }}</span></td>
                        <td class="wi-num"><span class="wi-pill {{ $log->errors ? 'wi-pill--red' : 'wi-pill--gray' }}">{{ $log->errors }}</span></td>
                        <td class="wi-num"><a href="{{ route('admin.whmcs-import.log.show', $log) }}" class="btn btn-sm wi-btn-outline">{{ __('whmcs_import.view_log') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($profiles->isNotEmpty())
    <div class="wi-card">
        <div class="wi-card__head">
            <span class="wi-card__icon"><i class="fas fa-bookmark"></i></span>
            <h2>{{ __('whmcs_import.saved_profiles') }}</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="wi-table">
                <thead><tr>
                    <th>{{ __('whmcs_import.profile_name') }}</th>
                    <th>{{ __('whmcs_import.source_table') }}</th>
                    <th>{{ __('whmcs_import.import_mode') }}</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @foreach($profiles as $profile)
                    <tr>
                        <td style="font-weight:600;">{{ $profile->name }}</td>
                        <td><span class="wi-tag">{{ $profile->source_table }}</span></td>
                        <td>{{ __('whmcs_import.modes.'.$profile->import_mode) }}</td>
                        <td class="wi-num" style="white-space:nowrap;">
                            @if($profile->connection_id)
                                <a href="{{ route('admin.whmcs-import.mapper', ['connection' => $profile->connection_id, 'profile' => $profile->id]) }}" class="btn btn-sm wi-btn-outline">{{ __('whmcs_import.apply_profile') }}</a>
                            @endif
                            <form method="POST" action="{{ route('admin.whmcs-import.profile.destroy', $profile) }}" style="display:inline;" onsubmit="return pnConfirm(event, @js(__('whmcs_import.delete_profile_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm wi-btn-danger-ghost" aria-label="{{ __('whmcs_import.delete_profile_confirm') }}"><i class="fas fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
