@extends("admin.layouts.app")
@section("title", "Currencies")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Currencies</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure supported currencies and exchange rates</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal-add-currency')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Currency
    </button>
</div>

@if($currencies->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No currencies configured" description="Add at least one currency to accept payments." icon="currency"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Code</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Prefix</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Suffix</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Rate (vs base)</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Default?</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($currencies as $currency)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                <td class="px-4 py-3">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $currency->code }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                    <code class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ $currency->prefix ?: '—' }}</code>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                    <code class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ $currency->suffix ?: '—' }}</code>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono">{{ number_format($currency->rate, 5) }}</td>
                <td class="px-4 py-3">
                    @if($currency->is_default)
                        <x-status-badge status="active" label="Default"/>
                    @else
                        <form method="POST" action="{{ route('admin.config.currencies.default', $currency) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Set Default</button>
                        </form>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                            x-data @click="$dispatch('open-modal-edit-currency-{{ $currency->id }}')"
                            class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4"/>
                        </button>
                        @if(!$currency->is_default)
                        <x-confirm-delete :action="route('admin.config.currencies.destroy', $currency)"
                            message="Delete this currency?"/>
                        @else
                        <span class="text-slate-300 dark:text-slate-600 cursor-not-allowed" title="Cannot delete the default currency">
                            <x-heroicon-o-trash class="w-4 h-4"/>
                        </span>
                        @endif
                    </div>
                </td>
            </tr>

            <x-modal :name="'edit-currency-' . $currency->id" title="Edit Currency" maxWidth="sm">
                <form method="POST" action="{{ route('admin.config.currencies.update', $currency) }}">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Currency Code</label>
                            <input type="text" name="code" value="{{ $currency->code }}" maxlength="3" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Prefix</label>
                                <input type="text" name="prefix" value="{{ $currency->prefix }}" maxlength="10"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Suffix</label>
                                <input type="text" name="suffix" value="{{ $currency->suffix }}" maxlength="10"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Exchange Rate</label>
                            <input type="number" name="rate" value="{{ $currency->rate }}" step="0.00001" min="0.00001" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button type="button" @click="$dispatch('close-modal-edit-currency-{{ $currency->id }}')"
                            class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Save Changes</button>
                    </div>
                </form>
            </x-modal>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<x-modal name="add-currency" title="Add Currency" maxWidth="sm">
    <form method="POST" action="{{ route('admin.config.currencies.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Currency Code <span class="text-slate-400">(e.g. USD, EUR, GBP)</span></label>
                <input type="text" name="code" maxlength="3" required placeholder="USD"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Prefix <span class="text-slate-400">(e.g. $)</span></label>
                    <input type="text" name="prefix" maxlength="10" placeholder="$"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Suffix <span class="text-slate-400">(e.g. USD)</span></label>
                    <input type="text" name="suffix" maxlength="10"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Exchange Rate <span class="text-slate-400">(vs base currency)</span></label>
                <input type="number" name="rate" step="0.00001" min="0.00001" required placeholder="1.00000"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-currency')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Add Currency</button>
        </div>
    </form>
</x-modal>

@endsection
