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
        @if($locale !== 'en')
        <form method="POST" action="{{ route('admin.config.languages.ai-translate', $locale) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('{{ __('admin.config.translations.ai_translate_confirm') }}')"><i class="fas fa-robot"></i> {{ __('admin.config.translations.ai_translate_missing') }}</button>
        </form>
        @endif
    </div>
</div>

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
                    $isAutoTranslated = false;
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
                        <i class="fas fa-robot" style="color:#f59e0b;" title="Auto-translated"></i>
                        @elseif($targetValue)
                        <i class="fas fa-check" style="color:#10b981;" title="Manual"></i>
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

@endsection
