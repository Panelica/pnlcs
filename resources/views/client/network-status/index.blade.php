@extends("client.layouts.app")
@section("title", __("client.network_status.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.network_status.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.network_status.subtitle') }}</p>
    </div>
</div>

@if($activeIssues->isEmpty())
<div class="pn-card mb-24">
    <div class="pn-card-body" style="text-align:center;padding:40px 20px">
        <div style="font-size:40px;line-height:1">&#9989;</div>
        <h3 style="margin:12px 0 4px;color:var(--success, #16a34a)">{{ __('client.network_status.all_operational') }}</h3>
        <p class="text-muted text-sm">{{ __('client.network_status.no_issues') }}</p>
    </div>
</div>
@else
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.network_status.active_issues') }}</span></div>
    <div class="pn-card-body-flush">
        @foreach($activeIssues as $issue)
        <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                <strong>{{ $issue->title }}</strong>
                <div style="display:flex;gap:6px">
                    <span class="badge badge-{{ strtolower($issue->priority) === 'critical' || strtolower($issue->priority) === 'high' ? 'overdue' : 'pending' }}">{{ __('client.network_status.priority_' . strtolower($issue->priority ?: 'medium')) }}</span>
                    <span class="badge badge-pending">{{ __('client.network_status.status_' . strtolower($issue->status ?: 'open')) }}</span>
                </div>
            </div>
            <p class="text-muted text-sm" style="margin:8px 0 4px;white-space:pre-wrap">{{ $issue->description }}</p>
            <small class="text-muted">
                {{ __('client.network_status.type_' . strtolower($issue->type ?: 'general')) }}
                @if($issue->affected_server) &nbsp;·&nbsp; {{ __('client.network_status.affecting') }}: {{ $issue->affected_server }} @endif
                @if($issue->start_date) &nbsp;·&nbsp; {{ __('client.network_status.since') }} {{ $issue->start_date->format('d M Y H:i') }} @endif
            </small>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($resolvedIssues->isNotEmpty())
<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.network_status.recently_resolved') }}</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.form.title') }}</th>
                    <th>{{ __('common.table.type') }}</th>
                    <th>{{ __('client.network_status.resolved_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resolvedIssues as $issue)
                <tr>
                    <td>{{ $issue->title }}</td>
                    <td class="text-muted text-sm">{{ __('client.network_status.type_' . strtolower($issue->type ?: 'general')) }}</td>
                    <td class="text-muted text-sm">{{ $issue->end_date?->format('d M Y H:i') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
