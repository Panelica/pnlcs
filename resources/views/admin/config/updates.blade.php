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
    $blockingCodes = array_column($report['blocking'] ?? [], 'code');
    $onlyMajor = $blockingCodes === ['major_version'];
@endphp

<style>
    .upd-hint{font-size:13px;color:var(--pn-muted,#777);margin:-6px 0 16px}
    .upd-hint code{background:rgba(0,0,0,.05);padding:1px 6px;border-radius:3px;font-size:12px}
    .upd-top{display:grid;grid-template-columns:1fr 2fr 1fr;gap:16px 24px}
    .upd-progress__head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px}
    .upd-progress__state{font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px}
    .upd-progress__state i{color:var(--theme-primary,#1a4d80)}
    .upd-progress__bar{height:10px;border-radius:999px;background:#e9edf2;overflow:hidden}
    .upd-progress__fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--theme-primary,#1a4d80),var(--theme-accent,#337ab7));transition:width .6s ease;background-size:200% 100%;animation:upd-flow 2s linear infinite}
    @keyframes upd-flow{from{background-position:200% 0}to{background-position:0 0}}
    .upd-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px 16px;list-style:none;margin:14px 0 0;padding:0}
    .upd-step{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--pn-muted,#999)}
    .upd-step__icon{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
    .upd-step--done{color:#2e7d32}.upd-step--done .upd-step__icon{background:#e3f4e5}
    .upd-step--current{color:inherit;font-weight:600}.upd-step--current .upd-step__icon{color:var(--theme-primary,#1a4d80)}
    .upd-top__label{display:block;font-size:12px;font-weight:600;color:var(--pn-muted,#777);margin:0 0 6px;line-height:16px;height:16px}
    .upd-top__control{display:flex;gap:8px;align-items:center;height:36px}
    .upd-top__control select,.upd-top__control .btn{height:36px;box-sizing:border-box;margin:0}
    .upd-top__control select{flex:1 1 auto;min-width:0}
    .upd-top__version{font-size:24px;font-weight:700;line-height:36px}
    .upd-top__note{font-size:12px;color:var(--pn-muted,#777);margin-top:6px;line-height:16px;min-height:16px}
    .upd-top__end{justify-content:flex-end}
    .upd-top__end-note{text-align:right}
    .upd-muted{font-size:12px;color:var(--pn-muted,#777)}
    @media (max-width:900px){.upd-top{grid-template-columns:1fr}.upd-top__end{justify-content:flex-start}.upd-top__end-note{text-align:left}.upd-top__label:empty,.upd-top__note:empty{display:none}.upd-top__control{height:auto;flex-wrap:wrap}}
    .upd-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .upd-section-title{font-size:14px;font-weight:600;margin:16px 0 6px}
    .upd-list{margin:0 0 8px;padding-left:18px;font-size:13px}
    .upd-list li{margin-bottom:4px;overflow-wrap:anywhere}
    .upd-paths{font-family:monospace;font-size:12px;margin:6px 0;padding-left:18px;overflow-wrap:anywhere}
    .upd-conflict{border:1px solid #e3e3e3;border-radius:6px;padding:12px 14px;margin-bottom:12px;background:#fff}
    .upd-conflict__path{font-family:monospace;font-size:13px;font-weight:600;overflow-wrap:anywhere}
    .upd-conflict__kind{font-size:12px;color:var(--pn-muted,#777);margin:2px 0 10px}
    .upd-choices{display:grid;gap:6px;margin:0 0 6px}
    .upd-choice{display:flex;gap:8px;align-items:flex-start;font-size:13px;font-weight:400;margin:0;cursor:pointer}
    .upd-choice input{margin-top:3px;flex-shrink:0}
    .upd-edit{margin-top:10px;border-top:1px dashed #ddd;padding-top:10px}
    .upd-edit textarea{width:100%;min-height:260px;font-family:monospace;font-size:12px;line-height:1.45;white-space:pre;overflow:auto;resize:vertical;box-sizing:border-box}
    .upd-upload{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;font-size:12px}
    .upd-upload input[type=file]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
    .upd-upload__name{color:var(--pn-muted,#777);overflow-wrap:anywhere}
    .upd-upload .btn{white-space:normal !important;max-width:100%;text-align:left !important}
</style>

<div class="page-header">
    <h1>{{ __('admin.updates.title') }}</h1>
</div>
<p class="upd-hint">{{ __('admin.updates.cli_hint_short') }} <code>php artisan pnlcs:update</code></p>

@if($unfinished)
<div class="alert alert-danger" style="margin-bottom:16px;">{{ __('admin.updates.unfinished', ['command' => 'php artisan pnlcs:update-rollback']) }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body upd-top">
        <div>
            <span class="upd-top__label">{{ __('admin.updates.installed') }}</span>
            <div class="upd-top__control"><span class="upd-top__version" data-installed>{{ $installed }}</span></div>
            <div class="upd-top__note">{{ $mode === 'git' ? __('admin.updates.mode_git') : '' }}</div>
        </div>
        <form method="POST" action="{{ route('admin.config.updates.channel') }}">
            @csrf
            <label class="upd-top__label" for="update-channel">{{ __('admin.updates.channel') }}</label>
            <div class="upd-top__control">
                <select name="channel" id="update-channel" class="form-control">
                    <option value="stable" @selected($channel === 'stable')>{{ __('admin.updates.channel_stable') }}</option>
                    <option value="beta" @selected($channel === 'beta')>{{ __('admin.updates.channel_beta') }}</option>
                </select>
                <button type="submit" class="btn btn-default">{{ __('admin.updates.save_channel') }}</button>
            </div>
            <div class="upd-top__note"></div>
        </form>
        <form method="POST" action="{{ route('admin.config.updates.check') }}">
            @csrf
            <span class="upd-top__label"></span>
            <div class="upd-top__control upd-top__end">
                <button type="submit" class="btn btn-primary" @disabled($active)>{{ __('admin.updates.check_now') }}</button>
            </div>
            <div class="upd-top__note upd-top__end-note">{{ isset($latest['checked_at']) ? __('admin.updates.last_checked', ['when' => $when($latest['checked_at'])]) : __('admin.updates.never_checked') }}</div>
        </form>
    </div>
</div>

@if(isset($latest['error']))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ __('admin.updates.check_failed', ['error' => $latest['error']]) }}</div>
@elseif($latest && ! $release)
<div class="alert alert-success" style="margin-bottom:16px;">{{ __('admin.updates.up_to_date', ['version' => $installed, 'channel' => __('admin.updates.channel_name.'.($latest['channel'] ?? $channel))]) }}</div>
@endif

@if($status && ($active || in_array($status['state'] ?? '', ['updated', 'refused', 'rolled_back', 'failed', 'rollback_failed', 'error'], true)))
@php
    $finalState = $status['state'] ?? '';
    $summary = in_array($finalState, ['updated', 'refused', 'rolled_back', 'failed', 'rollback_failed', 'error'], true) ? __('admin.updates.done.'.$finalState, ['version' => $status['version'] ?? '']) : null;
    $tone = ['updated' => 'alert-success', 'refused' => 'alert-warning', 'rolled_back' => 'alert-warning', 'failed' => 'alert-danger', 'rollback_failed' => 'alert-danger', 'error' => 'alert-danger'][$finalState] ?? 'alert-info';
    $progress = [
        'status' => $status,
        'statusUrl' => route('admin.config.updates.status'),
        'labels' => ['state' => __('admin.updates.status'), 'step' => __('admin.updates.step')],
        'steps' => [
            'apply' => ['download', 'check', 'maintenance', 'database', 'files', 'migrate', 'caches', 'health'],
            'prepare' => ['download', 'check'],
            'rolling_back' => ['files', 'database'],
        ],
    ];
@endphp
<div class="card" style="margin-bottom:16px;" id="update-progress" data-status-url="{{ route('admin.config.updates.status') }}" data-active="{{ $active ? '1' : '0' }}">
    <div class="card-header"><strong>{{ __('admin.updates.progress') }}</strong></div>
    <div class="card-body">
        @if($active)
        <div x-data="updateProgress(@js($progress))" x-init="start()">
            <div class="upd-progress__head">
                <span class="upd-progress__state"><i class="fas fa-circle-notch fa-spin"></i> <span x-text="stateLabel()"></span></span>
                <span class="upd-muted"><span>{{ __('admin.updates.elapsed') }}</span> <span x-text="elapsed"></span></span>
            </div>
            <div class="upd-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent()">
                <div class="upd-progress__fill" :style="'width:' + percent() + '%'"></div>
            </div>
            <ol class="upd-steps">
                <template x-for="(step, i) in steps()" :key="step">
                    <li :class="'upd-step upd-step--' + stepState(i)">
                        <span class="upd-step__icon">
                            <i class="fas fa-check" x-show="stepState(i) === 'done'"></i>
                            <i class="fas fa-circle-notch fa-spin" x-show="stepState(i) === 'current'"></i>
                            <i class="far fa-circle" x-show="stepState(i) === 'pending'"></i>
                        </span>
                        <span x-text="labels.step[step] || step"></span>
                    </li>
                </template>
            </ol>
            <div class="upd-muted" style="font-size:12px;margin-top:10px;">{{ __('admin.updates.progress_hint') }}</div>
        </div>
        @else
        <div class="alert {{ $tone }}" style="margin:0;">{{ $summary }}</div>
        @if(! empty($status['message']))
        <details style="margin-top:8px;"><summary class="upd-muted" style="cursor:pointer;font-size:12px;">{{ __('admin.updates.details') }}</summary><div style="font-size:12px;margin-top:6px;overflow-wrap:anywhere;font-family:monospace;">{{ $status['message'] }}</div></details>
        @endif
        @endif
        @if($late)
        <div class="alert alert-warning" style="margin:12px 0 0;">{{ __('admin.updates.scheduler_late', ['command' => 'php artisan pnlcs:update --from-request']) }}</div>
        @endif
    </div>
</div>
@endif

@if($release)
<div class="card" style="margin-bottom:16px;">
    <div class="card-header upd-row" style="justify-content:space-between;">
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
        <div class="upd-row">
            <form method="POST" action="{{ route('admin.config.updates.apply') }}" class="upd-row" onsubmit="return pnConfirm(event, @js(__('admin.updates.apply_confirm')))">
                @csrf
                @if($onlyMajor)
                <label class="upd-choice"><input type="checkbox" name="allow_major" value="1" required> <span>{{ __('admin.updates.allow_major') }}</span></label>
                @endif
                <button type="submit" class="btn btn-success" @disabled($active)><i class="fas fa-cloud-download-alt"></i> {{ __('admin.updates.apply') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.config.updates.prepare') }}">
                @csrf
                <button type="submit" class="btn btn-default" @disabled($active)>{{ __('admin.updates.check_only') }}</button>
            </form>
        </div>
        <p class="upd-muted" style="font-size:13px;margin:10px 0 0;">{{ __('admin.updates.apply_hint') }}</p>
    </div>
</div>
@endif

@if($report && $release)
<div class="card" style="margin-bottom:16px;" id="update-report">
    <div class="card-header"><strong>{{ __('admin.updates.report_title', ['version' => $report['to']]) }}</strong></div>
    <div class="card-body">
        <div class="alert {{ $report['ok'] ? 'alert-success' : 'alert-warning' }}" style="margin-bottom:8px;">{{ $report['ok'] ? __('admin.updates.report_ok') : __('admin.updates.report_blocked') }}</div>

        @if(! empty($report['blocking']))
        <div class="upd-section-title">{{ __('admin.updates.blocking') }}</div>
        <ul class="upd-list">
            @foreach($report['blocking'] as $issue)<li style="color:var(--pn-danger, #d9534f);">{{ $describe($issue) }}</li>@endforeach
        </ul>
        @endif

        @if(! empty($report['warnings']))
        <div class="upd-section-title">{{ __('admin.updates.warnings') }}</div>
        <ul class="upd-list">
            @foreach($report['warnings'] as $issue)<li>{{ $describe($issue) }}</li>@endforeach
        </ul>
        @endif

        <div class="upd-section-title">{{ __('admin.updates.your_changes') }}</div>
        <p style="font-size:13px;margin:0 0 8px;">{{ __('admin.updates.files_summary', ['write' => $report['plan']['write'] ?? 0, 'delete' => $report['plan']['delete'] ?? 0]) }}</p>
        @foreach(['merged' => 'merged_files', 'kept' => 'kept_files'] as $key => $label)
            @if(! empty($report['plan'][$key]))
            <details style="margin-bottom:6px;">
                <summary style="cursor:pointer;font-size:13px;">{{ __('admin.updates.'.$label, ['count' => count($report['plan'][$key])]) }}</summary>
                <ul class="upd-paths">@foreach($report['plan'][$key] as $path)<li>{{ $path }}</li>@endforeach</ul>
            </details>
            @endif
        @endforeach
        @if(! empty($report['plan']['resolved']))
        <details style="margin-bottom:6px;">
            <summary style="cursor:pointer;font-size:13px;">{{ __('admin.updates.resolved_files', ['count' => count($report['plan']['resolved'])]) }}</summary>
            <ul class="upd-paths">@foreach($report['plan']['resolved'] as $item)<li>{{ $item['path'] }} &middot; {{ __('admin.updates.choice_'.$item['resolution']) }}</li>@endforeach</ul>
        </details>
        @endif

        @if(! empty($report['conflicts']))
        <div class="upd-section-title" style="margin-top:20px;">{{ __('admin.updates.conflicts_title', ['count' => count($report['conflicts'])]) }}</div>
        <p class="upd-muted" style="font-size:13px;margin:0 0 10px;">{{ __('admin.updates.conflicts_hint', ['path' => 'storage/app/pnlcs-update/set-aside']) }}</p>
        <form method="POST" action="{{ route('admin.config.updates.resolve') }}" enctype="multipart/form-data">
            @csrf
            @foreach($report['conflicts'] as $i => $conflict)
                @php
                    $current = old("choice.{$i}", $choices[$conflict['path']] ?? '');
                    $canEdit = array_key_exists($conflict['path'], $editable);
                @endphp
                <div class="upd-conflict" x-data="{ choice: @js($current), fileName: '' }">
                    <div class="upd-conflict__path">{{ $conflict['path'] }}</div>
                    <div class="upd-conflict__kind">{{ __('admin.updates.conflict_kind.'.$conflict['kind']) }}</div>

                    @if($conflict['kind'] === 'operator_directory')
                        <div style="font-size:13px;">{{ __('admin.updates.operator_directory_hint') }}</div>
                    @else
                    <div class="upd-choices">
                        <label class="upd-choice"><input type="radio" name="choice[{{ $i }}]" value="" x-model="choice"> <span>{{ __('admin.updates.choice_pending') }}</span></label>
                        <label class="upd-choice"><input type="radio" name="choice[{{ $i }}]" value="new" x-model="choice"> <span>{{ __('admin.updates.choice_new') }}</span></label>
                        <label class="upd-choice"><input type="radio" name="choice[{{ $i }}]" value="mine" x-model="choice"> <span>{{ __('admin.updates.choice_mine') }}</span></label>
                        @if($canEdit)
                        <label class="upd-choice"><input type="radio" name="choice[{{ $i }}]" value="edited" x-model="choice"> <span>{{ __('admin.updates.choice_edited') }}</span></label>
                        @endif
                    </div>

                    @if($canEdit)
                    <div class="upd-edit" x-show="choice === 'edited'" x-cloak>
                        <div class="upd-muted" style="font-size:12px;margin-bottom:6px;">{{ __('admin.updates.editor_hint') }}</div>
                        <textarea name="resolved_text[{{ $i }}]" class="form-control" spellcheck="false" :disabled="choice !== 'edited'">{{ old("resolved_text.{$i}", $editable[$conflict['path']]) }}</textarea>
                        <div class="upd-upload">
                            <a href="{{ route('admin.config.updates.merged', ['path' => $conflict['path']]) }}" class="btn btn-default btn-xs"><i class="fas fa-download"></i> {{ __('admin.updates.download_merged') }}</a>
                            <span>{{ __('admin.updates.or_upload') }}</span>
                            <label class="btn btn-default btn-xs" style="margin:0;">
                                <i class="fas fa-upload"></i> {{ __('admin.updates.choose_file') }}
                                <input type="file" name="file[{{ $i }}]" @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                            </label>
                            <span class="upd-upload__name" x-text="fileName || @js(__('admin.updates.no_file'))"></span>
                        </div>
                    </div>
                    @endif
                    @endif
                </div>
            @endforeach
            <div class="upd-row">
                <button type="submit" name="then" value="apply" class="btn btn-success" @disabled($active) onclick="return pnConfirm(event, @js(__('admin.updates.apply_confirm')))"><i class="fas fa-cloud-download-alt"></i> {{ __('admin.updates.save_and_update') }}</button>
                <button type="submit" class="btn btn-default">{{ __('admin.updates.save_choices') }}</button>
            </div>
        </form>
        @endif
    </div>
</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body upd-row" style="justify-content:space-between;">
        <div style="flex:1 1 280px;">
            <div style="font-weight:600;font-size:13px;">{{ __('admin.updates.bar_setting') }}</div>
            <div class="upd-muted">{{ $barOff ? __('admin.updates.bar_setting_off') : __('admin.updates.bar_setting_on') }}</div>
        </div>
        <form method="POST" action="{{ route('admin.config.updates.bar') }}">
            @csrf
            <input type="hidden" name="show" value="{{ $barOff ? '1' : '0' }}">
            <button type="submit" class="btn btn-default btn-sm">{{ $barOff ? __('admin.updates.bar_turn_on') : __('admin.updates.bar_off') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.updates.history') }}</strong></div>
    @if($history === [])
    <div class="card-body" style="text-align:center;padding:24px;color:var(--pn-muted);">{{ __('admin.updates.history_empty') }}</div>
    @else
    <div style="overflow-x:auto;">
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
                <td style="white-space:nowrap;">{{ $when($entry['at'] ?? null) }}</td>
                <td>{{ $entry['from'] ?? '' }}</td>
                <td>{{ $entry['to'] ?? '' }}</td>
                <td>{{ $entry['by'] ?? '' }}</td>
                <td title="{{ $entry['message'] ?? '' }}">{{ __('admin.updates.history_result.'.($entry['result'] ?? 'failed')) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

@push('scripts')
<script>
function updateProgress(init) {
    return {
        status: init.status || {},
        labels: init.labels,
        elapsed: '0:00',
        steps() {
            var s = this.status;
            if (s.state === 'rolling_back') { return init.steps.rolling_back; }
            return init.steps[s.action === 'prepare' ? 'prepare' : 'apply'];
        },
        index() {
            var i = this.steps().indexOf(this.status.step);
            return this.status.state === 'queued' ? -1 : i;
        },
        stepState(i) {
            var current = this.index();
            if (i < current) { return 'done'; }
            return i === current ? 'current' : 'pending';
        },
        percent() {
            var n = this.steps().length, i = this.index();
            if (i < 0) { return 3; }
            return Math.max(3, Math.min(97, Math.round(((i + 0.5) / n) * 100)));
        },
        stateLabel() { return this.labels.state[this.status.state] || ''; },
        tick() {
            var start = Date.parse(this.status.started_at || this.status.updated_at || '');
            if (isNaN(start)) { return; }
            var sec = Math.max(0, Math.floor((Date.now() - start) / 1000));
            this.elapsed = Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
        },
        start() {
            var self = this, done = ['ready', 'updated', 'refused', 'rolled_back', 'failed', 'rollback_failed', 'error'];
            this.tick();
            setInterval(function () { self.tick(); }, 1000);
            (function poll() {
                fetch(init.statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (data && data.status) { self.status = data.status; }
                        if (data && !data.running && !data.request && done.indexOf((data.status || {}).state) !== -1) {
                            return window.location.reload();
                        }
                        setTimeout(poll, 2000);
                    })
                    // While the site is in maintenance the request fails; keep asking.
                    .catch(function () { setTimeout(poll, 3000); });
            })();
        },
    };
}
</script>
@endpush
@endsection
