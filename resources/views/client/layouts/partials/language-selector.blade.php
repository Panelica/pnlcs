@if(isset($activeLanguages) && $activeLanguages->count() > 1)
<div class="pn-nav-item" style="position:relative;">
    <button class="pn-nav-link" onclick="this.parentElement.classList.toggle('open')" style="gap:6px;">
        @if($activeLanguages->firstWhere('code', $currentLocale)?->flag_code)
        <img src="https://flagcdn.com/16x12/{{ $activeLanguages->firstWhere('code', $currentLocale)->flag_code }}.png" alt="" style="border-radius:1px;">
        @endif
        <span>{{ $currentLocaleName ?? 'English' }}</span>
        <svg class="pn-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="pn-dropdown" style="right:0;left:auto;min-width:160px;">
        @foreach($activeLanguages as $lang)
        <a href="?lang={{ $lang->code }}" style="{{ $lang->code === $currentLocale ? 'background:var(--primary-light);color:var(--primary);' : '' }}">
            @if($lang->flag_code)
            <img src="https://flagcdn.com/16x12/{{ $lang->flag_code }}.png" alt="" style="border-radius:1px;">
            @endif
            {{ $lang->native_name }}
        </a>
        @endforeach
    </div>
</div>
@endif
