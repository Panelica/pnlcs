@props(['value'])

@php
    $text = trim((string) $value);
@endphp

@if($text !== '')
<button type="button"
        class="copy-value"
        data-copy-value="{{ $text }}"
        title="{{ __('common.actions.copy') }}"
        aria-label="{{ __('common.actions.copy') }}"
        style="border:0;background:transparent;color:#337ab7;cursor:pointer;padding:0 0 0 6px;font-size:12px;line-height:1;vertical-align:middle;">
    <i class="far fa-copy" aria-hidden="true"></i>
</button>
@endif
