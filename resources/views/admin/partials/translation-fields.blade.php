{{-- Translations of operator-written content, one fold per active language
     other than the default (App\Support\ContentTranslations). $languages,
     $model (null on a new record, else with translations loaded), $fields:
     column => [label, 'input'|'textarea']. Leaving the first field empty
     removes that language's translation. --}}
@if(($languages ?? collect())->isNotEmpty())
<div class="form-group">
    <label class="form-label">{{ __('admin.content_translations.title') }}</label>
    <p style="font-size:12px;color:#777;margin:0 0 8px;">{{ __('admin.content_translations.hint') }}</p>
    @foreach($languages as $lang)
    @php($row = $model?->translations->firstWhere('language', $lang->code))
    <details style="border:1px solid #e5e5e5;border-radius:6px;padding:6px 10px;margin-bottom:6px;" @if($row) open @endif>
        <summary style="cursor:pointer;font-weight:600;">{{ $lang->native_name ?: $lang->name }} <span style="color:#999;font-weight:400;">({{ $lang->code }})</span>@if($row) <span class="badge-active" style="margin-left:6px;">{{ __('admin.content_translations.translated') }}</span>@endif</summary>
        @foreach($fields as $field => [$label, $type])
        <div class="form-group" style="margin-top:8px;">
            <label class="form-label">{{ $label }}</label>
            @if($type === 'textarea')
            <textarea name="translations[{{ $lang->code }}][{{ $field }}]" rows="5" class="form-control">{{ $row?->{$field} }}</textarea>
            @else
            <input type="text" name="translations[{{ $lang->code }}][{{ $field }}]" value="{{ $row?->{$field} }}" class="form-control">
            @endif
        </div>
        @endforeach
    </details>
    @endforeach
</div>
@endif
