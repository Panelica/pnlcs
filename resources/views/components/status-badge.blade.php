@props(['status' => 'unknown', 'size' => 'sm'])

@php
$colorMap = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'paid' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'open' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'unpaid' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'overdue' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'suspended' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'cancelled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
    'terminated' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'closed' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
    'fraud' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'answered' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    'customer-reply' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'in progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'new' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    'draft' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
    'expired' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
];
$color = $colorMap[strtolower($status)] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
$sizeClass = $size === 'xs' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full $sizeClass $color"]) }}>
    {{ ucfirst($status) }}
</span>
