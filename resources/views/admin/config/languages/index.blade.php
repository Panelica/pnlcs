@extends("admin.layouts.app")
@section("title", __("admin.config.languages.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.config.languages.title') }}</h1>
    <div style="display:flex;gap:8px;">
        <form method="POST" action="{{ route('admin.config.languages.cache-clear') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-sync"></i> {{ __('admin.config.languages.clear_cache') }}</button>
        </form>
    </div>
</div>

{{-- AI TRANSLATION - first on the page: the key and model every
     language's "Translate with AI" uses. Posts to the general-settings
     handler, which keeps the stored key when the field is left empty. --}}
@php
    $aiKeySaved = trim((string) \App\Models\Setting::get('OpenAIApiKey', '')) !== '';
    $currentModel = trim((string) \App\Models\Setting::get('OpenAIModel', '')) ?: \App\Services\AiTranslationService::DEFAULT_MODEL;
    $models = \App\Services\AiTranslationService::MODELS;
    if (! in_array($currentModel, $models, true)) { $models[] = $currentModel; }
@endphp
<div class="card" id="ai-settings" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong><i class="fas fa-robot"></i> {{ __('admin.config.languages.ai_settings') }}</strong>
        @if($aiKeySaved)
        <span class="badge badge-active">{{ __('admin.config.languages.ai_key_saved') }}</span>
        @else
        <span class="badge badge-pending">{{ __('admin.config.languages.ai_key_missing') }}</span>
        @endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.general.update') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            @csrf
            <div class="form-group" style="margin:0;flex:1;min-width:240px;max-width:420px;">
                <label class="form-label" for="ai-key">{{ __('admin.config.languages.openai_api_key') }}</label>
                {{-- The stored key is never echoed back: blank means keep. --}}
                <input type="password" id="ai-key" name="OpenAIApiKey" autocomplete="new-password" class="form-control"
                    placeholder="{{ $aiKeySaved ? __('admin.settings.smtp_password_keep') : 'sk-...' }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="ai-model">{{ __('admin.config.languages.openai_model') }}</label>
                <select id="ai-model" name="OpenAIModel" class="form-control" style="min-width:180px;">
                    @foreach($models as $model)
                    <option value="{{ $model }}" {{ $currentModel === $model ? 'selected' : '' }}>{{ $model }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        </form>
        <p style="margin:10px 0 0;font-size:12px;color:#666;">{{ __('admin.config.languages.ai_settings_hint') }}</p>
    </div>
</div>

{{-- Tab navigation --}}
<div style="display:flex;gap:4px;margin-bottom:16px;">
    <button class="btn btn-sm" onclick="showTab('languages')" id="tab-languages" style="background:var(--theme-primary,#1a4d80);color:#fff;">{{ __('admin.config.languages.tab_languages') }}</button>
    <button class="btn btn-default btn-sm" onclick="showTab('settings')" id="tab-settings">{{ __('admin.config.languages.tab_settings') }}</button>
</div>

{{-- LANGUAGES TAB --}}
<div id="panel-languages">
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('admin.config.languages.flag') }}</th>
                <th>{{ __('common.table.code') }}</th>
                <th>{{ __('common.table.name') }}</th>
                <th>{{ __('admin.config.languages.native_name') }}</th>
                <th>{{ __('admin.config.languages.direction') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('admin.config.languages.progress') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($languages as $lang)
            <tr>
                <td>
                    @if($lang->flag_code)
                    <img src="https://flagcdn.com/24x18/{{ $lang->flag_code }}.png" alt="{{ $lang->code }}" style="border-radius:2px;">
                    @else
                    <span style="color:#999;">-</span>
                    @endif
                </td>
                <td><code>{{ $lang->code }}</code></td>
                <td style="font-weight:600;">{{ $lang->name }}</td>
                <td>{{ $lang->native_name }}</td>
                <td><span class="badge {{ $lang->direction === 'rtl' ? 'badge-pending' : 'badge-active' }}">{{ strtoupper($lang->direction) }}</span></td>
                <td>
                    <form method="POST" action="{{ route('admin.config.languages.toggle', $lang) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-xs {{ $lang->is_active ? 'btn-success' : 'btn-default' }}">
                            {{ $lang->is_active ? __('common.status.active') : __('common.status.inactive') }}
                        </button>
                    </form>
                    @if($lang->is_default)
                    <span class="badge badge-active" style="margin-left:4px;">{{ __('common.status.default') }}</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="flex:1;background:#e5e7eb;border-radius:999px;height:6px;min-width:80px;overflow:hidden;">
                            <div style="height:100%;border-radius:999px;background:{{ $lang->translation_progress >= 100 ? '#10b981' : ($lang->translation_progress >= 50 ? '#f59e0b' : '#ef4444') }};width:{{ min($lang->translation_progress, 100) }}%;"></div>
                        </div>
                        <span style="font-size:12px;color:#666;white-space:nowrap;">{{ $lang->translation_progress }}%</span>
                    </div>
                </td>
                <td style="white-space:nowrap;">
                    @if($lang->code !== 'en')
                    <a href="{{ route('admin.config.languages.translations', $lang->code) }}" class="btn btn-default btn-xs"><i class="fas fa-edit"></i> {{ __('admin.config.languages.translate') }}</a>
                    @else
                    <a href="{{ route('admin.config.languages.translations', $lang->code) }}" class="btn btn-default btn-xs"><i class="fas fa-eye"></i> {{ __('admin.config.languages.view_keys') }}</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div style="padding:8px 0;font-size:13px;color:#666;">
    {{ __('admin.config.languages.total_keys', ['count' => $totalKeys, 'active' => $languages->where('is_active', true)->count()]) }}
</div>
</div>

{{-- SETTINGS TAB --}}
<div id="panel-settings" style="display:none;">
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.languages.set-default') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('admin.config.languages.default_language') }}</label>
                <select name="code" class="form-control" style="max-width:300px;">
                    @foreach($defaultCandidates as $lang)
                    <option value="{{ $lang->code }}" {{ $lang->is_default ? 'selected' : '' }}>{{ $lang->name }} ({{ $lang->native_name }}){{ $lang->is_active ? '' : ' — '.__('admin.config.languages.inactive_enabled_on_save') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        </form>
    </div>
</div>
</div>

<script>
function showTab(tab) {
    document.getElementById('panel-languages').style.display = tab === 'languages' ? '' : 'none';
    document.getElementById('panel-settings').style.display = tab === 'settings' ? '' : 'none';
    document.getElementById('tab-languages').className = tab === 'languages' ? 'btn btn-sm' : 'btn btn-default btn-sm';
    document.getElementById('tab-settings').className = tab === 'settings' ? 'btn btn-sm' : 'btn btn-default btn-sm';
    if (tab === 'languages') { document.getElementById('tab-languages').style.background = 'var(--theme-primary,#1a4d80)'; document.getElementById('tab-languages').style.color = '#fff'; document.getElementById('tab-settings').style.background = ''; document.getElementById('tab-settings').style.color = ''; }
    else { document.getElementById('tab-settings').style.background = 'var(--theme-primary,#1a4d80)'; document.getElementById('tab-settings').style.color = '#fff'; document.getElementById('tab-languages').style.background = ''; document.getElementById('tab-languages').style.color = ''; }
}
</script>
@endsection
