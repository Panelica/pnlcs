@props([
    'action' => '',
    'title' => 'Confirm Delete',
    'message' => 'Are you sure you want to delete this item? This action cannot be undone.',
    'buttonClass' => '',
])

<div x-data="{ confirming: false }" class="inline-flex">
    <button x-show="!confirming" x-on:click="confirming = true" type="button"
            class="{{ $buttonClass ?: 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300' }}">
        {{ $slot->isEmpty() ? '' : '' }}
        @if($slot->isEmpty())
            <x-heroicon-o-trash class="w-4 h-4"/>
        @else
            {{ $slot }}
        @endif
    </button>
    <div x-show="confirming" x-transition class="flex items-center gap-2">
        <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
        <form method="POST" action="{{ $action }}" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded transition">
                Delete
            </button>
        </form>
        <button x-on:click="confirming = false" type="button" class="px-2 py-1 bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs rounded transition">
            Cancel
        </button>
    </div>
</div>
