@props([
    'headers' => [],
    'rows' => [],
    'empty' => 'No records found.',
    'searchable' => false,
    'searchPlaceholder' => 'Search...',
    'createUrl' => null,
    'createLabel' => 'Add New',
    'paginator' => null,
])

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
    @if($searchable || $createUrl)
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        @if($searchable)
        <div class="relative w-full sm:w-72">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/>
            <input type="text" placeholder="{{ $searchPlaceholder }}"
                   class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                   x-data x-on:input.debounce.300ms="$dispatch('search', $event.target.value)"/>
        </div>
        @endif
        @if($createUrl)
        <a href="{{ $createUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            {{ $createLabel }}
        </a>
        @endif
    </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700">
                    @foreach($headers as $header)
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if($paginator && $paginator->hasPages())
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $paginator->links() }}
    </div>
    @endif
</div>
