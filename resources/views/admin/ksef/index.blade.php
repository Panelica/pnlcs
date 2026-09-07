@extends('admin.layouts.app')
@section('title', __('messages.ksef.settings_title'))
@section('content')
<div class="page-header">
    <h1>{{ __('messages.ksef.settings_title') }}</h1>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <p style="font-size:13px;color:var(--pn-muted);margin:0;">{{ __('messages.ksef.addon_output_hint') }}</p>
    <form method="POST" action="{{ route('admin.ksef.test') }}" style="margin:0;">
        @csrf
        <button type="submit" class="btn btn-success btn-sm" style="font-size:13px;padding:6px 14px;font-weight:600;">{{ __('messages.ksef.test') }}</button>
    </form>
</div>

@if($records->isEmpty())
    <p style="color:var(--pn-muted);font-size:13px;">{{ __('messages.ksef.none_yet') }}</p>
@else
    <div class="card">
        <div class="card-body" style="padding:0;">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr>
                    <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.invoice') }}</th>
                    <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.client') }}</th>
                    <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.total') }}</th>
                    <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.status') }}</th>
                    <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.ksef_number') }}</th>
                    <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.sent_at') }}</th>
                    <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.actions') }}</th>
                </tr>
                @foreach($records as $r)
                @php
                    $statusMap = [
                        'pending' => ['label' => __('messages.ksef.status_pending'), 'color' => '#f59e0b'],
                        'sent' => ['label' => __('messages.ksef.status_sent'), 'color' => '#337ab7'],
                        'accepted' => ['label' => __('messages.ksef.status_accepted'), 'color' => '#46a546'],
                        'rejected' => ['label' => __('messages.ksef.status_rejected'), 'color' => '#c43c35'],
                        'error' => ['label' => __('messages.ksef.status_error'), 'color' => '#c43c35'],
                        'corrected' => ['label' => __('messages.ksef.status_corrected'), 'color' => '#6c757d'],
                    ];
                    $status = $statusMap[$r->status] ?? ['label' => $r->status, 'color' => '#6c757d'];
                @endphp
                <tr>
                    <td style="padding:8px;">@if($r->invoice)<a href="{{ route('admin.invoices.show', $r->invoice) }}" style="color:#337ab7;">#{{ $r->invoice->invoice_num ?? $r->invoice->id }}</a>@else&mdash;@endif</td>
                    <td style="padding:8px;">{{ $r->invoice?->client?->display_name ?? '&mdash;' }}</td>
                    <td style="padding:8px;text-align:right;">{{ $r->invoice ? money_fmt($r->invoice->total) : '&mdash;' }}</td>
                    <td style="padding:8px;"><span style="display:inline-block;padding:2px 8px;border-radius:999px;background:{{ $status['color'] }}1a;color:{{ $status['color'] }};font-weight:600;font-size:12px;">{{ $status['label'] }}</span></td>
                    <td style="padding:8px;">{{ $r->ksef_number ?? '&mdash;' }}</td>
                    <td style="padding:8px;">{{ $r->sent_at ? $r->sent_at->format(date_fmt().' H:i') : '&mdash;' }}</td>
                    <td style="padding:8px;text-align:right;white-space:nowrap;">
                        @if(in_array($r->status, ['pending', 'error', 'rejected'], true))
                        <form method="POST" action="{{ route('admin.ksef.resend', $r) }}" style="display:inline;margin:0;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">{{ __('messages.ksef.resend') }}</button>
                        </form>
                        @endif
                        @if(in_array($r->status, ['sent', 'accepted'], true))
                        <form method="POST" action="{{ route('admin.ksef.mark-corrected', $r) }}" style="display:inline;margin:0;" onsubmit="return confirm('{{ __('messages.ksef.confirm_corrected') }}');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">{{ __('messages.ksef.mark_corrected') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @if($r->error_message)
                <tr><td colspan="7" style="padding:0 8px 8px;color:#c43c35;font-size:12px;">{{ $r->error_message }}</td></tr>
                @endif
                @endforeach
            </table>
        </div>
    </div>
    {{ $records->links() }}
@endif
@endsection
