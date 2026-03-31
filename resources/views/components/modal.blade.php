@props([
    'name' => 'modal',
    'title' => '',
    'maxWidth' => 'lg',
])

@php
$maxWidthClass = match($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    default => 'max-w-lg',
};
@endphp

<div x-data="{ open: false }"
     x-on:open-modal-{{ $name }}.window="open = true"
     x-on:close-modal-{{ $name }}.window="open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:leave="ease-in duration-150"
             class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>

        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl {{ $maxWidthClass }} w-full p-6 z-10">
            @if($title)
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                <button x-on:click="open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <x-heroicon-o-x-mark class="w-5 h-5"/>
                </button>
            </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
