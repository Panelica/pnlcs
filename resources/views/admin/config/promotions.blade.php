@extends("admin.layouts.app")
@section("title", "Promotions")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Promotions</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage discount codes and promotional offers</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal-add-promotion')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Promotion
    </button>
</div>

@if($promotions->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No promotions yet" description="Create promotional codes to offer discounts to customers." icon="megaphone"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Code</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Type</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Value</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Uses / Max</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Expiry</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Recurring</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Status</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($promotions as $promo)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                <td class="px-4 py-3">
                    <code class="font-mono font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">{{ $promo->code }}</code>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {{ match($promo->type) {
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        'free_setup' => 'Free Setup',
                        'price_override' => 'Price Override',
                        'override_recurring' => 'Override Recurring',
                        default => ucfirst(str_replace('_', ' ', $promo->type))
                    } }}
                </td>
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                    @if($promo->type === 'percentage')
                        {{ number_format($promo->value, 2) }}%
                    @else
                        {{ number_format($promo->value, 2) }}
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                    {{ $promo->uses }} / {{ $promo->max_uses ?: '∞' }}
                </td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">
                    {{ $promo->expiration_date ? $promo->expiration_date->format('M d, Y') : 'No expiry' }}
                </td>
                <td class="px-4 py-3">
                    @if($promo->recurring)
                        <x-status-badge status="active" label="Yes"/>
                    @else
                        <x-status-badge status="disabled" label="No"/>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($promo->isValid())
                        <x-status-badge status="active" label="Active"/>
                    @else
                        <x-status-badge status="disabled" label="Expired"/>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                            x-data @click="$dispatch('open-modal-edit-promo-{{ $promo->id }}')"
                            class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4"/>
                        </button>
                        <x-confirm-delete :action="route('admin.config.promotions.destroy', $promo)"
                            message="Delete this promotion?"/>
                    </div>
                </td>
            </tr>

            <x-modal :name="'edit-promo-' . $promo->id" title="Edit Promotion" maxWidth="lg">
                <form method="POST" action="{{ route('admin.config.promotions.update', $promo) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Promo Code</label>
                            <input type="text" name="code" value="{{ $promo->code }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                            <select name="type" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="percentage" @selected($promo->type === 'percentage')>Percentage</option>
                                <option value="fixed_amount" @selected($promo->type === 'fixed_amount')>Fixed Amount</option>
                                <option value="free_setup" @selected($promo->type === 'free_setup')>Free Setup</option>
                                <option value="price_override" @selected($promo->type === 'price_override')>Price Override</option>
                                <option value="override_recurring" @selected($promo->type === 'override_recurring')>Override Recurring</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Value</label>
                            <input type="number" name="value" value="{{ $promo->value }}" step="0.01" min="0" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Uses <span class="text-slate-400">(0 = unlimited)</span></label>
                            <input type="number" name="max_uses" value="{{ $promo->max_uses }}" min="0"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="{{ $promo->start_date?->format('Y-m-d') }}"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Expiry Date</label>
                            <input type="date" name="expiration_date" value="{{ $promo->expiration_date?->format('Y-m-d') }}"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                            <textarea name="notes" rows="2"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ $promo->notes }}</textarea>
                        </div>
                        <div class="col-span-2 flex items-center gap-3">
                            <input type="checkbox" name="recurring" id="recurring_{{ $promo->id }}" value="1"
                                @checked($promo->recurring)
                                class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"/>
                            <label for="recurring_{{ $promo->id }}" class="text-sm text-slate-700 dark:text-slate-300">Apply to recurring billing cycles</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button type="button" @click="$dispatch('close-modal-edit-promo-{{ $promo->id }}')"
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

<x-modal name="add-promotion" title="Add Promotion" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.promotions.store') }}">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Promo Code</label>
                <input type="text" name="code" required placeholder="SUMMER25"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                <select name="type" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="percentage">Percentage</option>
                    <option value="fixed_amount">Fixed Amount</option>
                    <option value="free_setup">Free Setup</option>
                    <option value="price_override">Price Override</option>
                    <option value="override_recurring">Override Recurring</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Value</label>
                <input type="number" name="value" step="0.01" min="0" required placeholder="25.00"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Uses <span class="text-slate-400">(0 = unlimited)</span></label>
                <input type="number" name="max_uses" min="0" value="0"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Start Date</label>
                <input type="date" name="start_date"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Expiry Date</label>
                <input type="date" name="expiration_date"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                <textarea name="notes" rows="2" placeholder="Internal notes about this promotion..."
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
            </div>
            <div class="col-span-2 flex items-center gap-3">
                <input type="checkbox" name="recurring" id="recurring_new" value="1"
                    class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"/>
                <label for="recurring_new" class="text-sm text-slate-700 dark:text-slate-300">Apply to recurring billing cycles</label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-promotion')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Create Promotion</button>
        </div>
    </form>
</x-modal>

@endsection
