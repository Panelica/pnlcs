@extends('admin.layouts.app')
@section('title', __('whmcs_import.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('whmcs_import.title') }}</h1>
</div>

<p style="color:#666;font-size:13px;margin-bottom:15px;">{{ __('whmcs_import.description') }}</p>

@if($errors->has('connection'))
    <div class="alert alert-danger">{{ $errors->first('connection') }}</div>
@endif

<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.connection_title') }}</div>
    <form method="POST" action="{{ route('admin.whmcs-import.connection.store') }}" style="padding:16px;">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.connection_name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.host') }}</label>
                <input type="text" name="host" value="{{ old('host') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.port') }}</label>
                <input type="number" name="port" value="{{ old('port', 3306) }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.database') }}</label>
                <input type="text" name="database" value="{{ old('database') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.username') }}</label>
                <input type="text" name="username" value="{{ old('username') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.password') }}</label>
                <input type="password" name="password" value="" class="form-control" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('whmcs_import.prefix') }}</label>
                <input type="text" name="prefix" value="{{ old('prefix', 'tbl') }}" class="form-control">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:14px;">
            <button type="submit" formaction="{{ route('admin.whmcs-import.connection.test') }}" class="btn btn-secondary btn-sm">{{ __('whmcs_import.test_connection') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('whmcs_import.save_and_continue') }}</button>
        </div>
    </form>
</div>

@if($connections->isNotEmpty())
<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.saved_connections') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('whmcs_import.connection_name') }}</th>
            <th>{{ __('whmcs_import.host') }}</th>
            <th>{{ __('whmcs_import.database') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($connections as $conn)
            <tr>
                <td>{{ $conn->name ?: ('#'.$conn->id) }}</td>
                <td>{{ $conn->host }}:{{ $conn->port }}</td>
                <td>{{ $conn->database }}</td>
                <td><a href="{{ route('admin.whmcs-import.mapper', $conn) }}" class="btn btn-primary btn-sm">{{ __('whmcs_import.open_mapper') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if($profiles->isNotEmpty())
<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.saved_profiles') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('whmcs_import.profile_name') }}</th>
            <th>{{ __('whmcs_import.source_table') }}</th>
            <th>{{ __('whmcs_import.import_mode') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($profiles as $profile)
            <tr>
                <td>{{ $profile->name }}</td>
                <td>{{ $profile->source_table }}</td>
                <td>{{ __('whmcs_import.modes.'.$profile->import_mode) }}</td>
                <td>
                    @if($profile->connection_id)
                        <a href="{{ route('admin.whmcs-import.mapper', ['connection' => $profile->connection_id, 'profile' => $profile->id]) }}" class="btn btn-secondary btn-sm">{{ __('whmcs_import.apply_profile') }}</a>
                    @endif
                    <form method="POST" action="{{ route('admin.whmcs-import.profile.destroy', $profile) }}" style="display:inline;" onsubmit="return pnConfirm(event, @js(__('whmcs_import.delete_profile_confirm')))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="color:#b91c1c;">&times;</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if($logs->isNotEmpty())
<div class="card">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.recent_logs') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('whmcs_import.log_date') }}</th>
            <th>{{ __('whmcs_import.source_table') }}</th>
            <th>{{ __('whmcs_import.log_total') }}</th>
            <th>{{ __('whmcs_import.log_added') }}</th>
            <th>{{ __('whmcs_import.log_updated') }}</th>
            <th>{{ __('whmcs_import.log_errors') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ $log->created_at }}</td>
                <td>{{ $log->source_table }}</td>
                <td>{{ $log->total }}</td>
                <td>{{ $log->added }}</td>
                <td>{{ $log->updated }}</td>
                <td>{{ $log->errors }}</td>
                <td><a href="{{ route('admin.whmcs-import.log.show', $log) }}" class="btn btn-secondary btn-sm">{{ __('whmcs_import.view_log') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
