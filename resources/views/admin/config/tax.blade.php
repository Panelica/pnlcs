@extends("admin.layouts.app")
@section("title", "Tax Rules")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Tax Rules</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure tax rates by country and region</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal-add-tax')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Tax Rule
    </button>
</div>

@if($rules->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No tax rules configured" description="Add tax rules to automatically apply taxes to invoices." icon="document"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Name</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Country</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">State/Region</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Rate %</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Level</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($rules as $rule)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $rule->name }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $rule->country ?: 'Any' }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $rule->state ?: 'Any' }}</td>
                <td class="px-4 py-3">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ number_format($rule->tax_rate, 2) }}%</span>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                    {{ $rule->level == 2 ? 'Level 2' : 'Level 1' }}
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                            x-data @click="$dispatch('open-modal-edit-tax-{{ $rule->id }}')"
                            class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4"/>
                        </button>
                        <x-confirm-delete :action="route('admin.config.tax.destroy', $rule)"
                            message="Delete this tax rule?"/>
                    </div>
                </td>
            </tr>

            <x-modal :name="'edit-tax-' . $rule->id" title="Edit Tax Rule" maxWidth="md">
                <form method="POST" action="{{ route('admin.config.tax.update', $rule) }}">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
                            <input type="text" name="name" value="{{ $rule->name }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Country Code <span class="text-slate-400">(2-letter, blank = all)</span></label>
                                <input type="text" name="country" value="{{ $rule->country }}" maxlength="2" placeholder="US"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">State <span class="text-slate-400">(blank = all)</span></label>
                                <input type="text" name="state" value="{{ $rule->state }}" placeholder="CA"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" value="{{ $rule->tax_rate }}" step="0.01" min="0" max="100" required
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Tax Level</label>
                                <select name="level"
                                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <option value="1" @selected($rule->level == 1)>Level 1</option>
                                    <option value="2" @selected($rule->level == 2)>Level 2</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button type="button" @click="$dispatch('close-modal-edit-tax-{{ $rule->id }}')"
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

<x-modal name="add-tax" title="Add Tax Rule" maxWidth="md">
    <form method="POST" action="{{ route('admin.config.tax.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
                <input type="text" name="name" required placeholder="e.g. US Sales Tax"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Country Code <span class="text-slate-400">(blank = all)</span></label>
                    <input type="text" name="country" maxlength="2" placeholder="US"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">State <span class="text-slate-400">(blank = all)</span></label>
                    <input type="text" name="state" placeholder="CA"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" step="0.01" min="0" max="100" required placeholder="10.00"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Tax Level</label>
                    <select name="level"
                        class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="1">Level 1</option>
                        <option value="2">Level 2</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-tax')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Add Tax Rule</button>
        </div>
    </form>
</x-modal>

@endsection
