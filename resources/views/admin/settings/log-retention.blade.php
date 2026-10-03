@extends('admin.layouts.app')
@section('title', __('admin.log_retention.title'))
@section('content')
<div class="page-header"><h1>{{ __('admin.log_retention.title') }}</h1></div>
<p style="font-size:13px;color:#777;margin-top:-6px;">{{ __('admin.log_retention.intro') }}</p>

<div class="card">
    <form method="POST" action="{{ route('admin.settings.log-retention.update') }}" id="retention-form">
        @csrf @method('PUT')
    </form>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('admin.log_retention.log') }}</th>
            <th>{{ __('admin.log_retention.rows') }}</th>
            <th>{{ __('admin.log_retention.oldest') }}</th>
            <th>{{ __('admin.log_retention.keep_days') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ __('admin.log_retention.table_'.$r['table']) }}</td>
            <td>{{ number_format($r['rows']) }}</td>
            <td style="font-size:12px;">{{ $r['oldest'] ? \Illuminate\Support\Carbon::parse($r['oldest'])->timezone(display_tz())->format(date_fmt()) : '—' }}</td>
            <td><input type="number" name="days[{{ $r['table'] }}]" form="retention-form" value="{{ $r['days'] }}" min="0" max="3650" class="form-control input-sm" style="width:100px;display:inline-block;"> <small style="color:#777;">{{ __('admin.log_retention.default', ['days' => $r['default']]) }}</small></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.settings.log-retention.prune') }}" style="margin:0;" onsubmit="return confirm('{{ __('admin.log_retention.confirm_prune') }}')">
                    @csrf
                    <input type="hidden" name="table" value="{{ $r['table'] }}">
                    <button type="submit" class="btn btn-default btn-xs" @disabled($r['days'] === 0)>{{ __('admin.log_retention.prune_now') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div class="card-body"><button type="submit" form="retention-form" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button></div>
</div>
@endsection
