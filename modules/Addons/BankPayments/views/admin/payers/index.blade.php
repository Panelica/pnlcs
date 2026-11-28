@extends('admin.layouts.app')
@section('title', __('bank-payments::matches.payers_title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><i class="fas fa-user-check"></i> {{ __('bank-payments::matches.payers_title') }}</h1>
        <p style="margin:4px 0 0;font-size:13px;color:var(--pn-muted);">{{ __('bank-payments::matches.payers_subtitle') }}</p>
    </div>
    <a href="{{ route('admin.bank-payments.matches.index') }}" class="btn btn-default btn-sm">&larr; {{ __('bank-payments::matches.title') }}</a>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body" style="padding:0;overflow-x:auto;">
        @if($profiles->isEmpty())
            <div style="padding:32px;text-align:center;color:var(--pn-muted);">{{ __('bank-payments::matches.payers_empty') }}</div>
        @else
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>{{ __('bank-payments::matches.sender') }}</th>
                    <th>{{ __('bank-payments::matches.client') }}</th>
                    <th>{{ __('bank-payments::matches.uses') }}</th>
                    <th>{{ __('bank-payments::matches.last_matched') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($profiles as $profile)
                <tr>
                    <td>{{ $profile->sender_name }}</td>
                    <td>{{ $profile->client?->company_name ?: $profile->client?->full_name }}</td>
                    <td>{{ $profile->uses }}</td>
                    <td>{{ $profile->last_matched_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('admin.bank-payments.payers.destroy', $profile) }}" onsubmit="return pnConfirm(event, @js(__('bank-payments::matches.payer_forget_confirm')))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('bank-payments::matches.payer_forget') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<div style="margin-top:14px;">{{ $profiles->links() }}</div>

@endsection
