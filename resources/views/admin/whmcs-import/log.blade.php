@extends('admin.layouts.app')
@section('title', __('whmcs_import.log.title'))
@section('content')
@include('admin.whmcs-import._styles')
@php
    $stats = [
        ['label' => __('whmcs_import.log.total'), 'value' => $log->total, 'icon' => 'fa-download', 'color' => '#64748b'],
        ['label' => __('whmcs_import.log.added'), 'value' => $log->added, 'icon' => 'fa-circle-plus', 'color' => '#16a34a'],
        ['label' => __('whmcs_import.log.updated'), 'value' => $log->updated, 'icon' => 'fa-rotate', 'color' => '#2563eb'],
        ['label' => __('whmcs_import.log.skipped'), 'value' => $log->skipped, 'icon' => 'fa-forward', 'color' => '#d97706'],
        ['label' => __('whmcs_import.log.errors'), 'value' => $log->errors, 'icon' => 'fa-circle-exclamation', 'color' => '#dc2626'],
    ];
@endphp

<div class="wi">
    <div class="wi-hero">
        <div class="wi-hero__row">
            <div class="wi-hero__icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="wi-hero__body">
                <h1>{{ __('whmcs_import.log.title') }}</h1>
                <div class="wi-chips" style="margin-top:8px;">
                    <span class="wi-chip"><i class="fas fa-database"></i> {{ $log->source }}</span>
                    <span class="wi-chip"><i class="fas fa-table"></i> {{ $log->source_table }}</span>
                    <span class="wi-chip"><i class="far fa-calendar"></i> {{ $log->created_at?->format('Y-m-d H:i') }}</span>
                </div>
            </div>
            <div class="wi-hero__back"><a href="{{ route('admin.whmcs-import.index') }}"><i class="fas fa-arrow-left"></i> {{ __('whmcs_import.title') }}</a></div>
        </div>
    </div>

    @include('admin.whmcs-import._steps', ['current' => 4])

    <div class="wi-stats">
        @foreach($stats as $stat)
            <div class="wi-stat" style="--c:{{ $stat['color'] }};">
                <div class="wi-stat__label"><i class="fas {{ $stat['icon'] }}"></i> {{ $stat['label'] }}</div>
                <div class="wi-stat__value">{{ number_format((int) $stat['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="wi-card">
        <div class="wi-card__head">
            <span class="wi-card__icon" style="background:#fef2f2;color:#dc2626;"><i class="fas fa-circle-exclamation"></i></span>
            <h2>{{ __('whmcs_import.log.error_details') }}</h2>
            @if(!empty($log->error_details))<span class="wi-card__aside"><span class="wi-pill wi-pill--red">{{ count($log->error_details) }}</span></span>@endif
        </div>
        @if(empty($log->error_details))
            <div class="wi-empty"><i class="fas fa-circle-check" style="color:#16a34a;"></i>{{ __('whmcs_import.log.no_errors') }}</div>
        @else
            <div style="overflow-x:auto;">
                <table class="wi-table">
                    <thead><tr>
                        <th>{{ __('whmcs_import.log.whmcs_id') }}</th>
                        <th>{{ __('whmcs_import.log.email') }}</th>
                        <th>{{ __('whmcs_import.log.error') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($log->error_details as $detail)
                        <tr>
                            <td><span class="wi-tag">{{ $detail['whmcs_id'] ?? '' }}</span></td>
                            <td>{{ $detail['email'] ?? '' }}</td>
                            <td style="color:#b91c1c;">{{ $detail['error'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="wi-card">
        <div class="wi-card__head">
            <span class="wi-card__icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-forward"></i></span>
            <h2>{{ __('whmcs_import.log.skipped_details') }}</h2>
            @if(!empty($log->skipped_details))<span class="wi-card__aside"><span class="wi-pill wi-pill--amber">{{ count($log->skipped_details) }}</span></span>@endif
        </div>
        @if(empty($log->skipped_details))
            <div class="wi-empty"><i class="fas fa-circle-check" style="color:#16a34a;"></i>{{ __('whmcs_import.log.no_skipped') }}</div>
        @else
            <div style="overflow-x:auto;">
                <table class="wi-table">
                    <thead><tr>
                        <th>{{ __('whmcs_import.log.whmcs_id') }}</th>
                        <th>{{ __('whmcs_import.log.email') }}</th>
                        <th>{{ __('whmcs_import.log.error') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($log->skipped_details as $detail)
                        <tr>
                            <td><span class="wi-tag">{{ $detail['whmcs_id'] ?? '' }}</span></td>
                            <td>{{ $detail['email'] ?? '' }}</td>
                            <td>{{ $detail['error'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
