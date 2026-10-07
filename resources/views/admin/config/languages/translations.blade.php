@extends("admin.layouts.app")
@section("title", __("admin.config.translations.title", ["name" => $language->name]))
@section("content")

<div class="page-header">
    <div>
        <a href="{{ route('admin.config.languages.index') }}" style="color:#337ab7;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> {{ __('admin.config.translations.back_to_languages') }}</a>
        <h1 style="margin-top:4px;">{{ __('admin.config.translations.heading', ['name' => $language->name, 'native' => $language->native_name]) }}</h1>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.config.languages.export', $locale) }}" class="btn btn-default btn-sm"><i class="fas fa-download"></i> {{ __('admin.config.translations.export_json') }}</a>
        <form method="POST" action="{{ route('admin.config.languages.import', $locale) }}" enctype="multipart/form-data" style="display:inline-flex;gap:4px;align-items:center;">
            @csrf
            <input type="file" name="file" accept=".json" style="font-size:12px;max-width:160px;">
            <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-upload"></i> {{ __('admin.config.translations.import') }}</button>
        </form>
    </div>
</div>

{{-- Translate with AI: the page asks for one batch at a time and shows
     each one as it lands, so the run can be watched and stopped. --}}
@if($locale !== 'en')
<div class="card" id="ai-panel" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong><i class="fas fa-robot"></i> {{ __('admin.config.translations.ai_title') }}</strong>
        @if($aiConfigured)
        <span style="font-size:12px;color:#666;">{{ __('admin.config.translations.ai_model', ['model' => $aiModel]) }}</span>
        @endif
    </div>
    <div class="card-body">
        @if(! $aiConfigured)
        <p style="margin:0;">{{ __('admin.config.translations.ai_not_configured') }}
            <a href="{{ route('admin.config.languages.index') }}#ai-settings">{{ __('admin.config.translations.ai_open_settings') }}</a></p>
        @else
        <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
            <label style="margin:0;font-weight:normal;cursor:pointer;">
                <input type="radio" name="ai_mode" value="missing" checked>
                {{ __('admin.config.translations.ai_mode_missing', ['count' => $aiCounts['missing']]) }}
            </label>
            <label style="margin:0;font-weight:normal;cursor:pointer;">
                <input type="radio" name="ai_mode" value="all">
                {{ __('admin.config.translations.ai_mode_all', ['count' => $aiCounts['all']]) }}
            </label>
            <div style="display:flex;gap:6px;margin-left:auto;">
                <button type="button" class="btn btn-primary btn-sm" id="ai-start"><i class="fas fa-play"></i> {{ __('admin.config.translations.ai_start') }}</button>
                <button type="button" class="btn btn-default btn-sm" id="ai-resume" style="display:none !important;"><i class="fas fa-forward"></i> {{ __('admin.config.translations.ai_resume') }}</button>
                <button type="button" class="btn btn-default btn-sm" id="ai-stop" style="display:none !important;"><i class="fas fa-stop"></i> {{ __('admin.config.translations.ai_stop') }}</button>
            </div>
        </div>
        <p style="margin:8px 0 0;font-size:12px;color:#666;">{{ __('admin.config.translations.ai_hint') }}</p>

        <div id="ai-run" style="display:none;margin-top:14px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="flex:1;background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
                    <div id="ai-bar" style="height:100%;width:0;background:var(--theme-primary,#1a4d80);transition:width .3s;"></div>
                </div>
                <span id="ai-count" style="font-size:12px;color:#666;white-space:nowrap;"></span>
            </div>
            <div id="ai-status" style="margin-top:8px;font-size:13px;"></div>
            <div style="margin-top:10px;max-height:420px;overflow:auto;border:1px solid #e5e7eb;border-radius:4px;">
                <table class="data-table" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="width:220px;">{{ __('admin.config.translations.key') }}</th>
                            <th>{{ __('admin.config.translations.source') }}</th>
                            <th>{{ __('admin.config.translations.target', ['name' => $language->name]) }}</th>
                        </tr>
                    </thead>
                    <tbody id="ai-log"></tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 16px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                <label class="form-label">{{ __('common.actions.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.config.translations.search_placeholder') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('admin.config.translations.group') }}</label>
                <select name="group" class="form-control" style="width:auto;">
                    <option value="">{{ __('admin.config.translations.all_groups') }}</option>
                    @foreach($groups as $g)
                    <option value="{{ $g }}" {{ request('group') === $g ? 'selected' : '' }}>{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-default">{{ __('common.actions.filter') }}</button>
                @if(request()->hasAny(['search', 'group']))
                <a href="{{ route('admin.config.languages.translations', $locale) }}" class="btn btn-default" style="margin-left:4px;">{{ __('common.actions.reset') }}</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Translation Table --}}
<form method="POST" action="{{ route('admin.config.languages.bulk-save', $locale) }}">
    @csrf
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:120px;">{{ __('admin.config.translations.group') }}</th>
                    <th style="width:200px;">{{ __('admin.config.translations.key') }}</th>
                    <th>{{ __('admin.config.translations.source') }}</th>
                    <th>{{ __('admin.config.translations.target', ['name' => $language->name]) }}</th>
                    <th style="width:50px;">{{ __('admin.config.translations.ai') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($englishKeys as $i => $enKey)
                @php
                    $fullKey = $enKey->group . '.' . $enKey->key;
                    $targetValue = $targetTranslations[$fullKey] ?? '';
                    $isAutoTranslated = isset($autoTranslated[$fullKey]);
                @endphp
                <tr>
                    <td><span style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;font-family:monospace;">{{ $enKey->group }}</span></td>
                    <td><code style="font-size:11px;">{{ $enKey->key }}</code></td>
                    <td style="color:#666;font-size:13px;">{{ $enKey->value }}</td>
                    <td>
                        <input type="hidden" name="translations[{{ $i }}][group]" value="{{ $enKey->group }}">
                        <input type="hidden" name="translations[{{ $i }}][key]" value="{{ $enKey->key }}">
                        <input type="text" name="translations[{{ $i }}][value]" value="{{ $targetValue }}"
                            class="form-control" style="font-size:13px;padding:5px 8px;"
                            placeholder="{{ $locale === 'en' ? '' : __('admin.config.translations.enter_translation') }}"
                            {{ $locale === 'en' ? 'readonly' : '' }}>
                    </td>
                    <td style="text-align:center;">
                        @if($targetValue && $isAutoTranslated)
                        <i class="fas fa-robot" style="color:#f59e0b;" title="{{ __('admin.config.translations.ai_marker') }}"></i>
                        @elseif($targetValue)
                        <i class="fas fa-check" style="color:#10b981;" title="{{ __('admin.config.translations.manual_marker') }}"></i>
                        @else
                        <i class="fas fa-minus" style="color:#d1d5db;"></i>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:10px 16px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
            {{ $englishKeys->withQueryString()->links() }}
            @if($locale !== 'en')
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('common.actions.save_changes') }}</button>
            @endif
        </div>
    </div>
</form>

@if($locale !== 'en' && $aiConfigured)
@push('scripts')
<script>
(function () {
    var url = @json(route('admin.config.languages.ai-translate-batch', $locale));
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var counts = @json($aiCounts);
    var text = {
        confirmAll: @json(__('admin.config.translations.ai_confirm_all', ['name' => $language->name])),
        progress: @json(__('admin.config.translations.ai_progress')),
        running: @json(__('admin.config.translations.ai_running')),
        stopped: @json(__('admin.config.translations.ai_stopped')),
        done: @json(__('admin.config.translations.ai_done')),
        nothing: @json(__('admin.config.translations.ai_nothing')),
        failed: @json(__('admin.config.translations.ai_request_failed')),
        skippedPlaceholders: @json(__('admin.config.translations.ai_skipped_placeholders')),
        skippedEmpty: @json(__('admin.config.translations.ai_skipped_empty')),
        reload: @json(__('admin.config.translations.ai_reload'))
    };
    var storeKey = 'pnlcs.ai-translate.' + @json($locale);
    var el = function (id) { return document.getElementById(id); };
    var run = null;

    function load() { try { return JSON.parse(localStorage.getItem(storeKey) || 'null'); } catch (e) { return null; } }
    function save() { try { localStorage.setItem(storeKey, JSON.stringify(run)); } catch (e) {} }
    function forget() { try { localStorage.removeItem(storeKey); } catch (e) {} }
    function fill(template, values) { return template.replace(/:(\w+)/g, function (m, k) { return values[k] !== undefined ? values[k] : m; }); }

    function paint() {
        var pct = run.total > 0 ? Math.min(100, Math.round(run.processed / run.total * 100)) : 100;
        el('ai-bar').style.width = pct + '%';
        el('ai-count').textContent = fill(text.progress, {done: run.processed, total: run.total, translated: run.translated, skipped: run.skipped});
    }

    function status(message, colour) {
        var box = el('ai-status');
        box.textContent = message;
        box.style.color = colour || '';
    }

    function row(key, source, value, note) {
        var tr = document.createElement('tr');
        [key, source, value].forEach(function (cell, i) {
            var td = document.createElement('td');
            td.textContent = cell;
            td.style.fontSize = i === 0 ? '11px' : '13px';
            if (i === 0) { td.style.fontFamily = 'monospace'; }
            if (i === 1) { td.style.color = '#666'; }
            if (note) { td.style.color = '#b45309'; }
            tr.appendChild(td);
        });
        var log = el('ai-log');
        log.insertBefore(tr, log.firstChild);
        while (log.children.length > 500) { log.removeChild(log.lastChild); }
    }

    // The theme sets .btn to display:inline-flex !important, so a plain
    // display:none on a button does nothing.
    function show(node, visible) {
        if (visible) { node.style.removeProperty('display'); } else { node.style.setProperty('display', 'none', 'important'); }
    }

    function buttons(running) {
        show(el('ai-start'), ! running);
        show(el('ai-stop'), running);
        show(el('ai-resume'), ! running && run && ! run.done);
        document.querySelectorAll('input[name="ai_mode"]').forEach(function (r) { r.disabled = running; });
    }

    function step() {
        if (! run || run.stop) { buttons(false); status(text.stopped); return; }
        fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({mode: run.mode, cursor: run.cursor})
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) { return {ok: response.ok, body: body}; });
        }).then(function (result) {
            if (! result.ok) { throw new Error(result.body.message || text.failed); }
            var data = result.body;
            data.items.forEach(function (item) { row(item.group + '.' + item.key, item.source, item.value); run.translated++; });
            data.skipped.forEach(function (item) {
                row(item.group + '.' + item.key, item.source, item.reason === 'placeholders' ? text.skippedPlaceholders : text.skippedEmpty, true);
                run.skipped++;
            });
            run.processed += data.items.length + data.skipped.length;
            run.total = Math.max(run.total, run.processed + data.remaining);
            run.cursor = data.cursor;
            run.done = data.done;
            save();
            paint();
            if (data.done) {
                forget();
                buttons(false);
                status(fill(text.done, {translated: run.translated, skipped: run.skipped}) + ' ' + text.reload, '#047857');
                return;
            }
            step();
        }).catch(function (error) {
            run.stop = true;
            save();
            buttons(false);
            status(error.message, '#b91c1c');
        });
    }

    function begin(state) {
        run = state;
        run.stop = false;
        el('ai-run').style.display = '';
        buttons(true);
        status(text.running);
        paint();
        step();
    }

    el('ai-start').addEventListener('click', function () {
        var mode = document.querySelector('input[name="ai_mode"]:checked').value;
        if (! counts[mode]) { el('ai-run').style.display = ''; status(text.nothing); return; }
        var go = function () {
            el('ai-log').innerHTML = '';
            begin({mode: mode, cursor: null, total: counts[mode], processed: 0, translated: 0, skipped: 0, done: false});
        };
        if (mode === 'all') { pnDialog.confirm(text.confirmAll).then(function (yes) { if (yes) { go(); } }); return; }
        go();
    });
    el('ai-resume').addEventListener('click', function () { if (run) { begin(run); } });
    el('ai-stop').addEventListener('click', function () { if (run) { run.stop = true; } });

    // A run interrupted by a closed tab carries on from its last batch.
    var saved = load();
    if (saved && ! saved.done && saved.mode) {
        run = saved;
        document.querySelectorAll('input[name="ai_mode"]').forEach(function (r) { r.checked = r.value === saved.mode; });
        el('ai-run').style.display = '';
        paint();
        status(text.stopped);
        buttons(false);
    }
})();
</script>
@endpush
@endif

@endsection
