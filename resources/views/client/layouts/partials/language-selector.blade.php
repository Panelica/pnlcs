@if(isset($activeLanguages) && $activeLanguages->count() > 1)
<div class="pn-nav-item" style="position:relative;">
    <button class="pn-nav-link" onclick="this.parentElement.classList.toggle('open')" style="gap:6px;">
        @php($currentFlag = $activeLanguages->firstWhere('code', $currentLocale)?->flag_code)
        <x-language-flag :code="$currentFlag" size="16x12" />
        <span class="pn-lang-name">{{ $currentLocaleName ?? 'English' }}</span>
        <svg class="pn-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="pn-dropdown" style="right:0;left:auto;min-width:160px;">
        @foreach($activeLanguages as $lang)
        <a href="{{ \App\Support\LocaleUrl::switchTo($lang->code) }}" style="{{ $lang->code === $currentLocale ? 'background:var(--primary-light);color:var(--primary);' : '' }}">
            @php($langFlag = $lang->flag_code)
            <x-language-flag :code="$langFlag" size="16x12" />
            {{ $lang->native_name }}
        </a>
        @endforeach
    </div>
</div>
@endif
