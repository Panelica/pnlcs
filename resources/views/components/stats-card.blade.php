@props([
    'title' => '',
    'value' => '0',
    'icon' => 'chart-bar',
    'color' => 'indigo',
    'change' => null,
    'href' => null,
])

@php
$colorMap = [
    'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'text' => 'text-indigo-600 dark:text-indigo-400'],
    'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
    'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400'],
    'red' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400'],
    'sky' => ['bg' => 'bg-sky-100 dark:bg-sky-900/30', 'text' => 'text-sky-600 dark:text-sky-400'],
    'purple' => ['bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-600 dark:text-purple-400'],
];
$colors = $colorMap[$color] ?? $colorMap['indigo'];
@endphp

@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 {{ $href ? 'hover:shadow-md transition-shadow' : '' }}">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $title }}</p>
            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $value }}</p>
            @if($change !== null)
            <p class="text-xs mt-1 {{ $change >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $change >= 0 ? '+' : '' }}{{ $change }}% from last month
            </p>
            @endif
        </div>
        <div class="flex-shrink-0 {{ $colors['bg'] }} rounded-lg p-3">
            @switch($icon)
                @case('users') <x-heroicon-o-users class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('currency') <x-heroicon-o-currency-dollar class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('server') <x-heroicon-o-server-stack class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('ticket') <x-heroicon-o-ticket class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('globe') <x-heroicon-o-globe-alt class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('document') <x-heroicon-o-document-text class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('shopping-cart') <x-heroicon-o-shopping-cart class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @case('inbox') <x-heroicon-o-inbox class="w-6 h-6 {{ $colors['text'] }}"/> @break
                @default <x-heroicon-o-chart-bar class="w-6 h-6 {{ $colors['text'] }}"/> @break
            @endswitch
        </div>
    </div>
</{{ $tag }}>
