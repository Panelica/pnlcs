@props(['code' => null, 'size' => '24x18', 'alt' => '', 'fallback' => 'none'])
@php
    // flagcdn serves ISO 3166-1 alpha-2 country codes and nothing else. A
    // regional code answers 404 - measured on es-ct (Catalan): both
    // flagcdn.com/24x18/es-ct.png and flagcdn.com/es-ct.svg - so asking it for
    // one drew a broken image in the language selector and an empty cell in
    // the admin list. A regional flag we know is drawn here; an unknown one
    // falls back to the code, or to nothing where a badge would sit oddly
    // beside a name.
    $flag = strtolower(trim((string) $code));
    [$w, $h] = array_pad(explode('x', (string) $size), 2, null);
    $w = (int) $w ?: 24;
    $h = (int) $h ?: 18;
@endphp
@if(preg_match('/^[a-z]{2}$/', $flag))
    <img src="https://flagcdn.com/{{ $w }}x{{ $h }}/{{ $flag }}.png" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}" style="border-radius:2px;">
@elseif($flag === 'es-ct')
    {{-- Senyera: nine bands, five yellow and four red. --}}
    <svg viewBox="0 0 9 6" width="{{ $w }}" height="{{ $h }}" role="img" aria-label="{{ $alt }}" style="border-radius:2px;display:inline-block;vertical-align:middle;">
        <rect width="9" height="6" fill="#FCDD09"/>
        <g fill="#DA121A">
            <rect y="0.6667" width="9" height="0.6667"/>
            <rect y="2" width="9" height="0.6667"/>
            <rect y="3.3333" width="9" height="0.6667"/>
            <rect y="4.6667" width="9" height="0.6667"/>
        </g>
    </svg>
@elseif($flag !== '' && $fallback === 'badge')
    <span style="font-size:10px;font-weight:700;color:#6b7280;background:#f3f4f6;border-radius:3px;padding:2px 5px;">{{ strtoupper($flag) }}</span>
@elseif($fallback === 'badge')
    <span style="color:#999;">-</span>
@endif
