@props([
    'title' => '''',
    'value' => '0',
    'color' => 'blue',
    'href' => null,
    'change' => null,
])

@php
$colorMap = [
    'green' => ['bg' => '#dff0d8', 'text' => '#3c763d'],
    'blue'  => ['bg' => '#d9edf7', 'text' => '#31708f'],
    'orange' => ['bg' => '#fcf8e3', 'text' => '#8a6d3b'],
    'red'   => ['bg' => '#f2dede', 'text' => '#a94442'],
    'purple' => ['bg' => '#e8e0f0', 'text' => '#5a3e82'],
    'teal'  => ['bg' => '#d0f0f0', 'text' => '#008b8b'],
];
$colors = $colorMap[$color] ?? $colorMap['blue'];
$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="stat-card"
    style="text-decoration:none; color:inherit; display:block;">
    <div style="width:40px; height:40px; background:{{ $colors['bg'] }}; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px;">
        {{ $slot }}
    </div>
    <div class="stat-value" style="color:{{ $colors['text'] }};">{{ $value }}</div>
    <div class="stat-label">{{ $title }}</div>
    @if($change !== null)
    <div style="font-size:11px; margin-top:4px; color:{{ $change >= 0 ? '#3c763d' : '#a94442' }};">
        {{ $change >= 0 ? '+' : '' }}{{ $change }}% vs last month
    </div>
    @endif
</{{ $tag }}>
