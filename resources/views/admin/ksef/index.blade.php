@extends('admin.layouts.app')
@section('title', __('messages.ksef.settings_title'))
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">{{ __('messages.ksef.settings_title') }}</h1>
        <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ __('messages.ksef.addon_output_hint') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.ksef.import') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">{{ __('messages.ksef.import') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.ksef.test') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">{{ __('messages.ksef.test') }}</button>
        </form>
    </div>
</div>

<div style="display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:18px;">
    @foreach([
        'sent' => __('messages.ksef.tab_sent'),
        'sales' => __('messages.ksef.tab_sales'),
        'costs' => __('messages.ksef.tab_costs'),
    ] as $tabKey => $tabLabel)
        <a href="{{ route('admin.ksef.index', ['tab' => $tabKey, 'year' => $year, 'month' => $month]) }}"
           style="padding:9px 16px;font-size:13px;text-decoration:none;color:{{ $tab === $tabKey ? 'var(--theme-primary, #1a4d80)' : '#555' }};font-weight:{{ $tab === $tabKey ? '600' : '400' }};border-bottom:3px solid {{ $tab === $tabKey ? 'var(--theme-primary, #1a4d80)' : 'transparent' }};margin-bottom:-2px;">
            {{ $tabLabel }}
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.ksef.index') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div>
        <label class="form-label" style="font-size:12px;">{{ __('messages.ksef.filter_year') }}</label>
        <select name="year" class="form-control">
            @foreach($years as $optionYear)
                <option value="{{ $optionYear }}" @selected($year === $optionYear)>{{ $optionYear }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" style="font-size:12px;">{{ __('messages.ksef.filter_month') }}</label>
        <select name="month" class="form-control">
            @foreach($months as $optionMonth)
                <option value="{{ $optionMonth }}" @selected($month === $optionMonth)>{{ str_pad((string) $optionMonth, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-default btn-sm">{{ __('messages.ksef.filter') }}</button>
</form>

@if($records->hasPages())
<div style="margin-bottom:12px;">{{ $records->withQueryString()->links() }}</div>
@endif

@if($records->isEmpty())
    <p style="color:var(--pn-muted);font-size:13px;">{{ __('messages.ksef.none_in_period') }}</p>
@else
    <div class="card">
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    @if($tab === 'sent')
                        <tr>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.invoice') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.client') }}</th>
                            <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.total') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.status') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.ksef_number') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.sent_at') }}</th>
                            <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.actions') }}</th>
                        </tr>
                    @else
                        <tr>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.invoice_number') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.issue_date') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ $tab === 'sales' ? __('messages.ksef.buyer') : __('messages.ksef.seller') }}</th>
                            <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.net') }}</th>
                            <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.vat') }}</th>
                            <th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.gross') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.ksef_number') }}</th>
                            <th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">{{ __('messages.ksef.imported_at') }}</th>
                        </tr>
                    @endif

                    @foreach($records as $r)
                        @if($tab === 'sent')
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
                                <td style="padding:8px;">{{ $r->invoice?->client?->display_name ?? '—' }}</td>
                                <td style="padding:8px;text-align:right;">{{ $r->invoice ? money_fmt($r->invoice->total) : '—' }}</td>
                                <td style="padding:8px;"><span style="display:inline-block;padding:2px 8px;border-radius:999px;background:{{ $status['color'] }}1a;color:{{ $status['color'] }};font-weight:600;font-size:12px;">{{ $status['label'] }}</span></td>
                                <td style="padding:8px;">{{ $r->ksef_number ?? '—' }}</td>
                                <td style="padding:8px;">{{ $r->sent_at ? $r->sent_at->format(date_fmt().' H:i') : '—' }}</td>
                                <td style="padding:8px;text-align:right;white-space:nowrap;">
                                    @if(in_array($r->status, ['pending', 'error', 'rejected'], true))
                                    <form method="POST" action="{{ route('admin.ksef.resend', $r) }}" style="display:inline;margin:0;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">{{ __('messages.ksef.resend') }}</button>
                                    </form>
                                    @endif
                                    @if(in_array($r->status, ['sent', 'accepted'], true))
                                    <form method="POST" action="{{ route('admin.ksef.mark-corrected', $r) }}" style="display:inline;margin:0;" onsubmit="return pnConfirm(event, @js(__('messages.ksef.confirm_corrected')));">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">{{ __('messages.ksef.mark_corrected') }}</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @if($r->error_message)
                            <tr><td colspan="7" style="padding:0 8px 8px;color:#c43c35;font-size:12px;">{{ $r->error_message }}</td></tr>
                            @endif
                        @else
                            @php($party = $tab === 'sales' ? ($r->buyer_name ?: $r->buyer_nip) : ($r->seller_name ?: $r->seller_nip))
                            <tr>
                                <td style="padding:8px;">{{ $r->invoice_number ?? '—' }}</td>
                                <td style="padding:8px;">{{ $r->issue_date?->format(date_fmt()) ?? '—' }}</td>
                                <td style="padding:8px;">{{ $party ?: '—' }}</td>
                                <td style="padding:8px;text-align:right;">{{ $r->net !== null ? number_format((float) $r->net, 2, ',', ' ') : '—' }}</td>
                                <td style="padding:8px;text-align:right;">{{ $r->vat !== null ? number_format((float) $r->vat, 2, ',', ' ') : '—' }}</td>
                                <td style="padding:8px;text-align:right;">{{ $r->gross !== null ? number_format((float) $r->gross, 2, ',', ' ') : '—' }}</td>
                                <td style="padding:8px;">{{ $r->ksef_number }}</td>
                                <td style="padding:8px;">{{ $r->imported_at?->format(date_fmt().' H:i') ?? '—' }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            </div>
        </div>
    </div>
@endif

@if($records->hasPages())
<div style="margin-top:14px;">{{ $records->withQueryString()->links() }}</div>
@endif
@endsection
