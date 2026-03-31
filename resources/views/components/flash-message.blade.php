@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)"
     class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-center justify-between">
    <div class="flex items-center gap-2">
        <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500"/>
        <span class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</span>
    </div>
    <button x-on:click="show = false" class="text-emerald-400 hover:text-emerald-600">
        <x-heroicon-o-x-mark class="w-4 h-4"/>
    </button>
</div>
@endif

@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-transition
     class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
    <div class="flex items-center gap-2">
        <x-heroicon-s-x-circle class="w-5 h-5 text-red-500"/>
        <span class="text-sm text-red-700 dark:text-red-400">{{ session('error') }}</span>
    </div>
    <button x-on:click="show = false" class="text-red-400 hover:text-red-600">
        <x-heroicon-o-x-mark class="w-4 h-4"/>
    </button>
</div>
@endif

@if($errors->any())
<div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
    <div class="flex items-center gap-2 mb-2">
        <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-500"/>
        <span class="text-sm font-medium text-red-700 dark:text-red-400">Please fix the following errors:</span>
    </div>
    <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400 space-y-1">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
