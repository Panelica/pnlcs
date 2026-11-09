{{-- A small Markdown toolbar over the plain textarea. No editor library and no
     build step: the value is stored as Markdown and rendered with Str::markdown
     told to treat its input as text (Product::descriptionHtml), so a stray tag
     is shown as text, not run. --}}
@php
    $editorName = $name ?? 'description';
    $editorId = $id ?? 'md-'.preg_replace('/[^a-z0-9_-]+/i', '-', $editorName);
@endphp
<div data-md-wrap>
    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">
        <button type="button" class="btn btn-default btn-sm" data-md="bold" title="{{ __('admin.products.md_bold') }}"><b>B</b></button>
        <button type="button" class="btn btn-default btn-sm" data-md="italic" title="{{ __('admin.products.md_italic') }}"><i>I</i></button>
        <button type="button" class="btn btn-default btn-sm" data-md="heading" title="{{ __('admin.products.md_heading') }}">H2</button>
        <button type="button" class="btn btn-default btn-sm" data-md="ul" title="{{ __('admin.products.md_bullet_list') }}">&bull;</button>
        <button type="button" class="btn btn-default btn-sm" data-md="ol" title="{{ __('admin.products.md_numbered_list') }}">1.</button>
        <button type="button" class="btn btn-default btn-sm" data-md="link" title="{{ __('admin.products.md_link') }}">{{ __('admin.products.md_link') }}</button>
        <button type="button" class="btn btn-default btn-sm" data-md="quote" title="{{ __('admin.products.md_quote') }}">&ldquo;</button>
        <button type="button" class="btn btn-default btn-sm" data-md="code" title="{{ __('admin.products.md_inline_code') }}"><code>&lt;/&gt;</code></button>
    </div>
    <textarea id="{{ $editorId }}" name="{{ $editorName }}" rows="{{ $rows ?? 4 }}" class="form-control" data-md-textarea>{{ $value ?? '' }}</textarea>
    <small class="text-muted" style="display:block;margin-top:4px;">{{ __('admin.products.description_markdown_hint') }}</small>
</div>

@once
@push('scripts')
<script>
(function () {
    function wrap(textarea, before, after) {
        var start = textarea.selectionStart, end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);
        textarea.value = value.slice(0, start) + before + selected + after + value.slice(end);
        textarea.focus();
        textarea.selectionStart = start + before.length;
        textarea.selectionEnd = start + before.length + selected.length;
    }
    function prefixLines(textarea, prefix) {
        var value = textarea.value;
        var start = textarea.selectionStart, end = textarea.selectionEnd;
        var from = value.lastIndexOf('\n', start - 1) + 1;
        var to = value.indexOf('\n', end);
        if (to === -1) { to = value.length; }
        var block = value.slice(from, to).split('\n').map(function (line) { return prefix + line; }).join('\n');
        textarea.value = value.slice(0, from) + block + value.slice(to);
        textarea.focus();
        textarea.selectionStart = from;
        textarea.selectionEnd = from + block.length;
    }
    function insertLink(textarea) {
        var start = textarea.selectionStart, end = textarea.selectionEnd;
        var value = textarea.value;
        var label = value.slice(start, end) || 'text';
        var inserted = '[' + label + '](https://)';
        textarea.value = value.slice(0, start) + inserted + value.slice(end);
        textarea.focus();
        textarea.selectionStart = start + label.length + 3;
        textarea.selectionEnd = start + label.length + 11;
    }
    document.querySelectorAll('[data-md-wrap]').forEach(function (wrapEl) {
        var textarea = wrapEl.querySelector('[data-md-textarea]');
        if (!textarea) { return; }
        wrapEl.querySelectorAll('[data-md]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-md');
                if (action === 'bold') { wrap(textarea, '**', '**'); }
                else if (action === 'italic') { wrap(textarea, '*', '*'); }
                else if (action === 'code') { wrap(textarea, '`', '`'); }
                else if (action === 'heading') { prefixLines(textarea, '## '); }
                else if (action === 'ul') { prefixLines(textarea, '- '); }
                else if (action === 'ol') { prefixLines(textarea, '1. '); }
                else if (action === 'quote') { prefixLines(textarea, '> '); }
                else if (action === 'link') { insertLink(textarea); }
            });
        });
        textarea.addEventListener('keydown', function (event) {
            if (! (event.ctrlKey || event.metaKey)) { return; }
            if (event.key === 'b' || event.key === 'B') { event.preventDefault(); wrap(textarea, '**', '**'); }
            if (event.key === 'i' || event.key === 'I') { event.preventDefault(); wrap(textarea, '*', '*'); }
        });
    });
})();
</script>
@endpush
@endonce
