@extends("admin.layouts.app")
@section("title", __("admin.updates.title"))
@section("content")

@php
    $describe = function (array $issue): string {
        $params = array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $issue['params'] ?? []);

        return __('admin.updates.issue.'.$issue['code'], $params);
    };
    $when = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format(date_fmt().' H:i') : '';
    $release = $latest['latest'] ?? null;
    $active = ($request !== null) || $running || in_array($status['state'] ?? '', ['queued', 'preparing', 'applying', 'rolling_back'], true);
    $late = $request && \Illuminate\Support\Carbon::parse($request['requested_at'])->lt(now()->subMinutes(2));
    $command = 'php artisan pnlcs:update';
    $blockingCodes = array_column($report['blocking'] ?? [], 'code');
    $onlyMajor = $blockingCodes === ['major_version'];
@endphp

<div class="page-header">
    <h1>{{ __('admin.updates.title') }}</h1>
    <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ __('admin.updates.cli_hint', ['command' => $command]) }}</p>
</div>

@if($unfinished)
<div class="alert alert-danger" style="margin-bottom:16px;">{{ __('admin.updates.unfinished', ['command' => 'php artisan pnlcs:update-rollback']) }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-end;justify-content:space-between;">
        <div>
            <div style="font-size:12px;color:var(--pn-muted);">{{ __('admin.updates.installed') }}</div>
            <div style="font-size:22px;font-weight:700;" data-installed>{{ $installed }}</div>
            <div style="font-size:12px;color:var(--pn-muted);">{{ isset($latest['checked_at']) ? __('admin.updates.last_checked', ['when' => $when($latest['checked_at'])]) : __('admin.updates.never_checked') }}</div>
        </div>
        <form method="POST" action="{{ route('admin.config.updates.channel') }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            @csrf
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="update-channel">{{ __('admin.updates.channel') }}</label>
                <select name="channel" id="update-channel" class="form-control">
                    <option value="stable" @selected($channel === 'stable')>{{ __('admin.updates.channel_stable') }}</option>
                    <option value="beta" @selected($channel === 'beta')>{{ __('admin.updates.channel_beta') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-default btn-sm">{{ __('admin.updates.save_channel') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.config.updates.check') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" @disabled($active)>{{ __('admin.updates.check_now') }}</button>
        </form>
    </div>
</div>

@if(isset($latest['error']))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ __('admin.updates.check_failed', ['error' => $latest['error']]) }}</div>
@elseif($latest && ! $release)
<div class="alert alert-success" style="margin-bottom:16px;">{{ __('admin.updates.up_to_date', ['version' => $installed, 'channel' => $latest['channel'] ?? $channel]) }}</div>
@endif

@if($status && ($active || in_array($status['state'] ?? '', ['updated', 'refused', 'rolled_back', 'failed', 'rollback_failed', 'error'], true)))
<div class="card" style="margin-bottom:16px;" id="update-progress" data-status-url="{{ route('admin.config.updates.status') }}" data-active="{{ $active ? '1' : '0' }}">
    <div class="card-header"><strong>{{ __('admin.updates.progress') }}</strong></div>
    <div class="card-body">
        <div style="font-weight:600;" data-status-state>{{ __('admin.updates.status.'.($status['state'] ?? 'queued')) }}</div>
        <div style="font-size:13px;color:var(--pn-muted);" data-status-step>{{ __('admin.updates.step.'.($status['step'] ?? 'download')) }}</div>
        @if(! empty($status['message']))
        <div style="font-size:13px;margin-top:8px;">{{ $status['message'] }}</div>
        @endif
        @if($late)
        <div class="alert alert-warning" style="margin:12px 0 0;">{{ __('admin.updates.scheduler_late', ['command' => 'php artisan pnlcs:update --from-request']) }}</div>
        @endif
    </div>
</div>
@endif

@if($release)
<div class="card" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong>{{ __('admin.updates.available', ['version' => $release['version']]) }}
            @if($release['pre_release'])<span class="badge badge-warning" style="margin-left:6px;">{{ __('admin.updates.beta_badge') }}</span>@endif
        </strong>
        @if(! empty($release['url']))<a href="{{ $release['url'] }}" target="_blank" rel="noopener" class="btn btn-default btn-xs">{{ __('admin.updates.view_release') }}</a>@endif
    </div>
    <div class="card-body">
        @if(trim($release['notes'] ?? '') !== '')
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-weight:600;">{{ __('admin.updates.release_notes') }}</summary>
            <div style="white-space:pre-wrap;font-size:13px;margin-top:8px;max-height:360px;overflow:auto;">{{ $release['notes'] }}</div>
        </details>
        @endif
        <form method="POST" action="{{ route('admin.config.updates.prepare') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" @disabled($active)>{{ __('admin.updates.prepare') }}</button>
            <span style="font-size:13px;color:var(--pn-muted);">{{ __('admin.updates.prepare_hint') }}</span>
        </form>
    </div>
</div>
@endif

@if($report && $release)
<div class="card" style="margin-bottom:16px;" id="update-report">
    <div class="card-header"><strong>{{ __('admin.updates.report_title', ['version' => $report['to']]) }}</strong></div>
    <div class="card-body">
        <div class="alert {{ $report['ok'] ? 'alert-success' : 'alert-warning' }}">{{ $report['ok'] ? __('admin.updates.report_ok') : __('admin.updates.report_blocked') }}</div>

        @if(! empty($report['blocking']))
        <h4 style="font-size:14px;margin:12px 0 6px;">{{ __('admin.updates.blocking') }}</h4>
        <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;">
            @foreach($report['blocking'] as $issue)<li style="color:var(--pn-danger, #d9534f);">{{ $describe($issue) }}</li>@endforeach
        </ul>
        @endif

        @if(! empty($report['warnings']))
        <h4 style="font-size:14px;margin:12px 0 6px;">{{ __('admin.updates.warnings') }}</h4>
        <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;">
            @foreach($report['warnings'] as $issue)<li>{{ $describe($issue) }}</li>@endforeach
        </ul>
        @endif

        <h4 style="font-size:14px;margin:12px 0 6px;">{{ __('admin.updates.your_changes') }}</h4>
        <p style="font-size:13px;margin:0 0 8px;">{{ __('admin.updates.files_summary', ['write' => $report['plan']['write'] ?? 0, 'delete' => $report['plan']['delete'] ?? 0]) }}</p>
        @foreach(['merged' => 'merged_files', 'kept' => 'kept_files'] as $key => $label)
            @if(! empty($report['plan'][$key]))
            <details style="margin-bottom:6px;">
                <summary style="cursor:pointer;font-size:13px;">{{ __('admin.updates.'.$label, ['count' => count($report['plan'][$key])]) }}</summary>
                <ul style="font-family:monospace;font-size:12px;margin:6px 0;padding-left:18px;">@foreach($report['plan'][$key] as $path)<li>{{ $path }}</li>@endforeach</ul>
            </details>
            @endif
        @endforeach
        @if(! empty($report['plan']['resolved']))
        <details style="margin-bottom:6px;">
            <summary style="cursor:pointer;font-size:13px;">{{ __('admin.updates.resolved_files', ['count' => count($report['plan']['resolved'])]) }}</summary>
            <ul style="font-family:monospace;font-size:12px;margin:6px 0;padding-left:18px;">@foreach($report['plan']['resolved'] as $item)<li>{{ $item['path'] }} ({{ __('admin.updates.choice_'.$item['resolution']) }})</li>@endforeach</ul>
        </details>
        @endif

        @if(! empty($report['conflicts']))
        <h4 style="font-size:14px;margin:16px 0 6px;">{{ __('admin.updates.conflicts_title', ['count' => count($report['conflicts'])]) }}</h4>
        <p style="font-size:13px;color:var(--pn-muted);margin:0 0 8px;">{{ __('admin.updates.conflicts_hint', ['path' => 'storage/app/pnlcs-update/set-aside']) }}</p>
        <form method="POST" action="{{ route('admin.config.updates.resolve') }}" enctype="multipart/form-data">
            @csrf
            <table class="data-table">
                <tbody>
                @foreach($report['conflicts'] as $i => $conflict)
                    @php $current = $choices[$conflict['path']] ?? ''; @endphp
                    <tr>
                        <td style="vertical-align:top;">
                            <div style="font-family:monospace;font-size:13px;">{{ $conflict['path'] }}</div>
                            <div style="font-size:12px;color:var(--pn-muted);">{{ __('admin.updates.conflict_kind.'.$conflict['kind']) }}</div>
                            @if(! empty($conflict['has_merged']))
                            <a href="{{ route('admin.config.updates.merged', ['path' => $conflict['path']]) }}" style="font-size:12px;">{{ __('admin.updates.download_merged') }}</a>
                            @endif
                        </td>
                        <td style="vertical-align:top;font-size:13px;">
                            @if($conflict['kind'] === 'operator_directory')
                                {{ __('admin.updates.conflict_kind.operator_directory') }}
                            @else
                            <label style="display:block;font-weight:400;"><input type="radio" name="choice[{{ $i }}]" value="" @checked($current === '')> {{ __('admin.updates.choice_pending') }}</label>
                            <label style="display:block;font-weight:400;"><input type="radio" name="choice[{{ $i }}]" value="new" @checked($current === 'new')> {{ __('admin.updates.choice_new') }}</label>
                            <label style="display:block;font-weight:400;"><input type="radio" name="choice[{{ $i }}]" value="mine" @checked($current === 'mine')> {{ __('admin.updates.choice_mine') }}</label>
                            <label style="display:block;font-weight:400;"><input type="radio" name="choice[{{ $i }}]" value="edited" @checked($current === 'edited')> {{ __('admin.updates.choice_edited') }}</label>
                            <label style="display:block;font-size:12px;margin-top:4px;">{{ __('admin.updates.upload_edited') }} <input type="file" name="file[{{ $i }}]"></label>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <button type="submit" class="btn btn-default btn-sm" style="margin-top:8px;">{{ __('admin.updates.save_choices') }}</button>
        </form>
        @endif

        @if($report['ok'] || $onlyMajor)
        <form method="POST" action="{{ route('admin.config.updates.apply') }}" style="margin-top:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm(@js(__('admin.updates.apply_confirm')))">
            @csrf
            @if($onlyMajor)
            <label style="font-size:13px;font-weight:400;"><input type="checkbox" name="allow_major" value="1" required> {{ __('admin.updates.allow_major') }}</label>
            @endif
            <button type="submit" class="btn btn-success btn-sm" @disabled($active)>{{ __('admin.updates.apply') }}</button>
        </form>
        @endif
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><strong>{{ __('admin.updates.history') }}</strong></div>
    @if($history === [])
    <div class="card-body" style="text-align:center;padding:24px;color:var(--pn-muted);">{{ __('admin.updates.history_empty') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('admin.updates.col_date') }}</th>
            <th>{{ __('admin.updates.col_from') }}</th>
            <th>{{ __('admin.updates.col_to') }}</th>
            <th>{{ __('admin.updates.col_by') }}</th>
            <th>{{ __('admin.updates.col_result') }}</th>
        </tr></thead>
        <tbody>
        @foreach($history as $entry)
            <tr>
                <td>{{ $when($entry['at'] ?? null) }}</td>
                <td>{{ $entry['from'] ?? '' }}</td>
                <td>{{ $entry['to'] ?? '' }}</td>
                <td>{{ $entry['by'] ?? '' }}</td>
                <td title="{{ $entry['message'] ?? '' }}">{{ __('admin.updates.history_result.'.($entry['result'] ?? 'failed')) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@push('scripts')
<script>
(function () {
    var box = document.getElementById('update-progress');
    if (!box || box.dataset.active !== '1') { return; }
    var labels = { state: @js(__('admin.updates.status')), step: @js(__('admin.updates.step')) };
    var done = ['ready', 'updated', 'refused', 'rolled_back', 'failed', 'rollback_failed', 'error'];
    function poll() {
        fetch(box.dataset.statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return setTimeout(poll, 5000); }
                var s = data.status || {};
                box.querySelector('[data-status-state]').textContent = labels.state[s.state] || s.state || '';
                box.querySelector('[data-status-step]').textContent = labels.step[s.step] || s.step || '';
                if (!data.running && !data.request && done.indexOf(s.state) !== -1) { return window.location.reload(); }
                setTimeout(poll, 3000);
            })
            // While the site is in maintenance the request fails; keep asking.
            .catch(function () { setTimeout(poll, 5000); });
    }
    setTimeout(poll, 3000);
})();
</script>
@endpush
@endsection
