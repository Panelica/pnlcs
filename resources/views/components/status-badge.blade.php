@props(['status' => 'unknown', 'size' => 'sm'])

@php
$statusClass = 'badge-'. strtolower(str_replace(' ', '-', $status));
$padStyle = $size === 'xs' ? 'padding:1px 6px; font-size:10px;' : 'padding:2px 8px;';
@endphp

<span {{ $attributes->merge(['class' => 'badge '. $statusClass]) }} style="{{ $padStyle }}">
    {{ ucfirst($status) }}
</span>
