@extends("admin.layouts.app")
@section("title", __("admin.config.languages"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.config.languages') }}</h1>
    <div style="display:flex;gap:8px;">
        <form method="POST" action="{{ route('admin.config.languages.cache-clear') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-sync"></i> Clear Cache</button>
        </form>
    </div>
</div>

{{-- Tab navigation --}}
<div style="display:flex;gap:4px;margin-bottom:16px;">
    <button class="btn btn-sm" onclick="showTab('languages')" id="tab-languages" style="background:var(--theme-primary,#1a4d80);color:#fff;">Languages</button>
    <button class="btn btn-default btn-sm" onclick="showTab('settings')" id="tab-settings">Settings</button>
</div>

{{-- LANGUAGES TAB --}}
<div id="panel-languages">
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Flag</th>
                <th>{{ __('common.table.code') }}</th>
                <th>{{ __('common.table.name') }}</th>
                <th>Native Name</th>
                <th>Direction</th>
                <th>{{ __('common.table.status') }}</th>
                <th>Progress</th>
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
                    <a href="{{ route('admin.config.languages.translations', $lang->code) }}" class="btn btn-default btn-xs"><i class="fas fa-edit"></i> Translate</a>
                    <form method="POST" action="{{ route('admin.config.languages.ai-translate', $lang->code) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-xs btn-primary" onclick="return confirm('AI translate all missing keys for {{ $lang->name }}?')">
                            <i class="fas fa-robot"></i> AI
                        </button>
                    </form>
                    @else
                    <a href="{{ route('admin.config.languages.translations', $lang->code) }}" class="btn btn-default btn-xs"><i class="fas fa-eye"></i> View Keys</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div style="padding:8px 0;font-size:13px;color:#666;">
    Total: {{ $totalKeys }} translation keys across {{ $languages->where('is_active', true)->count() }} active languages.
</div>
</div>

{{-- SETTINGS TAB --}}
<div id="panel-settings" style="display:none;">
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.languages.set-default') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Default Language</label>
                <select name="code" class="form-control" style="max-width:300px;">
                    @foreach($languages->where('is_active', true) as $lang)
                    <option value="{{ $lang->code }}" {{ $lang->is_default ? 'selected' : '' }}>{{ $lang->name }} ({{ $lang->native_name }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        </form>

        <hr style="margin:24px 0;">

        <h4 style="margin-bottom:12px;">AI Translation Settings</h4>
        <form method="POST" action="{{ route('admin.settings.general.update') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">OpenAI API Key</label>
                <input type="password" name="settings[OpenAIApiKey]" class="form-control" style="max-width:400px;"
                    value="{{ \App\Models\Setting::get('OpenAIApiKey', '') }}" placeholder="sk-...">
            </div>
            <div class="form-group">
                <label class="form-label">OpenAI Model</label>
                <select name="settings[OpenAIModel]" class="form-control" style="max-width:300px;">
                    @php $currentModel = \App\Models\Setting::get('OpenAIModel', 'gpt-4o-mini'); @endphp
                    <option value="gpt-4o-mini" {{ $currentModel === 'gpt-4o-mini' ? 'selected' : '' }}>GPT-4o Mini (Cheapest)</option>
                    <option value="gpt-4o" {{ $currentModel === 'gpt-4o' ? 'selected' : '' }}>GPT-4o (Best Quality)</option>
                    <option value="gpt-3.5-turbo" {{ $currentModel === 'gpt-3.5-turbo' ? 'selected' : '' }}>GPT-3.5 Turbo (Legacy)</option>
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
