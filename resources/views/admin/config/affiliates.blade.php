@extends('admin.layouts.app')
@section('title', __('admin.affiliates.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.affiliates.affiliate_program') }}</h1>
</div>
<div class="card">
    @if(($affiliates ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.affiliates.no_affiliates_registered') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.client') }}</th><th>{{ __('admin.affiliates.visits') }}</th><th>{{ __('admin.affiliates.earnings') }}</th><th>{{ __('admin.affiliates.balance') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
        <tbody>
        @foreach($affiliates as $aff)
        <tr>
            <td>
                @if($aff->client)
                    <a href="{{ $aff->client ? route("admin.clients.show", $aff->client) : "#" }}" style="color:#337ab7;font-weight:600;">{{ $aff->client?->first_name ?? "Deleted" }} {{ $aff->client?->last_name ?? "Client" }}</a>
                @else
                    <span style="color:#999;">{{ __('admin.affiliates.deleted_client') }} (#{{ $aff->client_id }})</span>
                @endif
            </td>
            <td>{{ $aff->visitors ?? 0 }}</td>
            <td>${{ number_format($aff->pay_amount ?? 0, 2) }}</td>
            <td style="font-weight:600;color:#5cb85c;">${{ number_format($aff->balance ?? 0, 2) }}</td>
            <td><span style="padding:2px 8px;border-radius:4px;font-size:12px;background:{{ $aff->balance > 0 ? '#d4edda' : '#f8f9fa' }};color:{{ $aff->balance > 0 ? '#155724' : '#6c757d' }}">{{ $aff->balance > 0 ? __('admin.affiliates.active') : __('admin.affiliates.inactive') }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
