@extends('admin.layouts.app')
@section('title', __('whmcs_import.log.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('whmcs_import.log.title') }}</h1>
    <a href="{{ route('admin.whmcs-import.index') }}" class="btn btn-secondary btn-sm">&larr; {{ __('whmcs_import.title') }}</a>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.log.title') }}</div>
    <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
        <div><strong>{{ __('whmcs_import.log.source') }}:</strong> {{ $log->source }}</div>
        <div><strong>{{ __('whmcs_import.log.table') }}:</strong> {{ $log->source_table }}</div>
        <div><strong>{{ __('whmcs_import.log.total') }}:</strong> {{ $log->total }}</div>
        <div><strong>{{ __('whmcs_import.log.added') }}:</strong> {{ $log->added }}</div>
        <div><strong>{{ __('whmcs_import.log.updated') }}:</strong> {{ $log->updated }}</div>
        <div><strong>{{ __('whmcs_import.log.skipped') }}:</strong> {{ $log->skipped }}</div>
        <div><strong>{{ __('whmcs_import.log.errors') }}:</strong> {{ $log->errors }}</div>
    </div>
</div>

<div class="card">
    <div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ __('whmcs_import.log.error_details') }}</div>
    @if(empty($log->error_details))
        <div style="padding:16px;color:#666;">{{ __('whmcs_import.log.no_errors') }}</div>
    @else
        <table class="table">
            <thead><tr>
                <th>{{ __('whmcs_import.log.whmcs_id') }}</th>
                <th>{{ __('whmcs_import.log.email') }}</th>
                <th>{{ __('whmcs_import.log.error') }}</th>
            </tr></thead>
            <tbody>
            @foreach($log->error_details as $detail)
                <tr>
                    <td>{{ $detail['whmcs_id'] ?? '' }}</td>
                    <td>{{ $detail['email'] ?? '' }}</td>
                    <td style="color:#b91c1c;">{{ $detail['error'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
