{{-- Translations of a knowledge base text, one fold per active language other
     than the default. $languages, $model (null on a new article), $fields:
     field => [label, 'input'|'textarea', required-when-used]. An empty title
     or name removes that language's translation. --}}
@if(($languages ?? collect())->isNotEmpty())
<div class="form-group">
    <label class="form-label">{{ __('admin.knowledge_base.translations') }}</label>
    <p style="font-size:12px;color:#777;margin:0 0 8px;">{{ __('admin.knowledge_base.translations_hint') }}</p>
    @foreach($languages as $lang)
    @php($row = $model?->translations->firstWhere('language', $lang->code))
    <details style="border:1px solid #e5e5e5;border-radius:6px;padding:6px 10px;margin-bottom:6px;" @if($row) open @endif>
        <summary style="cursor:pointer;font-weight:600;">{{ $lang->native_name ?: $lang->name }} <span style="color:#999;font-weight:400;">({{ $lang->code }})</span>@if($row) <span class="badge-active" style="margin-left:6px;">{{ __('admin.knowledge_base.translated') }}</span>@endif</summary>
        @foreach($fields as $field => [$label, $type])
        <div class="form-group" style="margin-top:8px;">
            <label class="form-label">{{ $label }}</label>
            @if($type === 'textarea')
            <textarea name="translations[{{ $lang->code }}][{{ $field }}]" rows="6" class="form-control">{{ $row?->{$field} }}</textarea>
            @else
            <input type="text" name="translations[{{ $lang->code }}][{{ $field }}]" value="{{ $row?->{$field} }}" class="form-control">
            @endif
        </div>
        @endforeach
    </details>
    @endforeach
</div>
@endif
