@props([
    'title' => 'No data found',
    'description' => '',
    'icon' => 'inbox',
    'actionUrl' => null,
    'actionLabel' => 'Create',
])

<div class="text-center py-12 px-4">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
        @switch($icon)
            @case('inbox')
                <x-heroicon-o-inbox class="w-8 h-8 text-slate-400"/>
                @break
            @case('users')
                <x-heroicon-o-users class="w-8 h-8 text-slate-400"/>
                @break
            @case('ticket')
                <x-heroicon-o-ticket class="w-8 h-8 text-slate-400"/>
                @break
            @case('document')
                <x-heroicon-o-document-text class="w-8 h-8 text-slate-400"/>
                @break
            @case('server')
                <x-heroicon-o-server-stack class="w-8 h-8 text-slate-400"/>
                @break
            @case('globe')
                <x-heroicon-o-globe-alt class="w-8 h-8 text-slate-400"/>
                @break
            @case('currency')
                <x-heroicon-o-currency-dollar class="w-8 h-8 text-slate-400"/>
                @break
            @case('shield')
                <x-heroicon-o-shield-exclamation class="w-8 h-8 text-slate-400"/>
                @break
            @case('megaphone')
                <x-heroicon-o-megaphone class="w-8 h-8 text-slate-400"/>
                @break
            @default
                <x-heroicon-o-inbox class="w-8 h-8 text-slate-400"/>
        @endswitch
    </div>
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">{{ $title }}</h3>
    @if($description)
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @if($actionUrl)
    <a href="{{ $actionUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        {{ $actionLabel }}
    </a>
    @endif
</div>
