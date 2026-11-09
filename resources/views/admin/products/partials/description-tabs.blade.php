{{-- The product description, one tab per active language. The default
     language edits the product's own description; every other tab edits a
     translation row (App\Support\ContentTranslations), with a button that
     starts it from the default text. --}}
@php
    // The same default the rest of the content translations use (the
    // DefaultLanguage setting), not the Language row's is_default flag.
    $defaultCode = \App\Support\LocaleUrl::defaultLocale();
    $defaultLanguage = \App\Models\Language::where('code', $defaultCode)->first();
    $defaultLabel = $defaultLanguage?->native_name ?: ($defaultLanguage?->name ?: strtoupper($defaultCode));
    $languages = $translationLanguages ?? collect();
    $editorRows = $rows ?? 5;
@endphp
<div class="form-group" x-data="{ lang: '{{ $defaultCode }}' }">
    <label class="form-label">{{ __('common.form.description') }}</label>
    @if($languages->isNotEmpty())
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
        <button type="button" class="btn btn-sm" :class="lang === '{{ $defaultCode }}' ? 'btn-primary' : 'btn-default'" @click="lang = '{{ $defaultCode }}'">
            {{ $defaultLabel }} <span style="opacity:.7;">({{ $defaultCode }})</span>
        </button>
        @foreach($languages as $lang)
        @php($row = $product?->translations?->firstWhere('language', $lang->code))
        <button type="button" class="btn btn-sm" :class="lang === '{{ $lang->code }}' ? 'btn-primary' : 'btn-default'" @click="lang = '{{ $lang->code }}'">
            {{ $lang->native_name ?: $lang->name }} <span style="opacity:.7;">({{ $lang->code }})</span>
            @if($row)<span class="badge-active" style="margin-left:4px;">{{ __('admin.content_translations.translated') }}</span>@endif
        </button>
        @endforeach
    </div>
    @endif

    <div x-show="lang === '{{ $defaultCode }}'" x-cloak>
        @include('admin.products.partials.markdown-editor', ['name' => 'description', 'id' => 'md-desc-default', 'value' => $value, 'rows' => $editorRows])
    </div>

    @foreach($languages as $lang)
    @php($row = $product?->translations?->firstWhere('language', $lang->code))
    <div x-show="lang === '{{ $lang->code }}'" x-cloak>
        @include('admin.products.partials.markdown-editor', [
            'name' => "translations[{$lang->code}][description]",
            'id' => 'md-desc-'.$lang->code,
            'value' => old("translations[{$lang->code}][description]", $row?->description),
            'rows' => $editorRows,
        ])
        <div style="margin-top:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <button type="button" class="btn btn-default btn-sm" onclick="var d=document.getElementById('md-desc-default'),t=document.getElementById('md-desc-{{ $lang->code }}');if(d&&t){t.value=d.value;t.focus();}">
                {{ __('admin.products.description_copy_default') }}
            </button>
            <small class="text-muted">{{ __('admin.content_translations.hint') }}</small>
        </div>
    </div>
    @endforeach
</div>
