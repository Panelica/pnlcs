@extends('admin.layouts.app')
@section('title', 'Domain Pricing')
@section('content')

<div x-data="{
    showModal: false,
    editMode: false,
    form: {
        id: null, extension: '', register_price: '', transfer_price: '', renew_price: '',
        grace_period: 0, auto_registrar: '', sort_order: 0, enabled: true,
        min_years: 1, max_years: 10
    },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, extension: '', register_price: '', transfer_price: '', renew_price: '',
            grace_period: 0, auto_registrar: '', sort_order: 0, enabled: true, min_years: 1, max_years: 10 };
        this.showModal = true;
    },
    openEdit(tld) {
        this.editMode = true;
        this.form = { ...tld };
        this.showModal = true;
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Domain Pricing</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Configure TLD registration, transfer and renewal pricing</p>
        </div>
        <button @click="openAdd()" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add TLD
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($tlds->isEmpty())
            <x-empty-state icon="globe" title="No domain pricing configured"
                description="Add TLD extensions to enable domain registration for your clients."/>
        @else
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">TLD Extension</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Register</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Transfer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Renew</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Grace Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Registrar</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Auto-Register</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sort</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($tlds as $tld)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-6 py-4">
                        <span class="font-mono font-semibold text-sm text-slate-900 dark:text-white">{{ $tld->extension }}</span>
                        @if(!$tld->enabled)
                            <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">Disabled</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        {{ isset($tld->register_price) ? '$'.number_format($tld->register_price, 2) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        {{ isset($tld->transfer_price) ? '$'.number_format($tld->transfer_price, 2) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        {{ isset($tld->renew_price) ? '$'.number_format($tld->renew_price, 2) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $tld->grace_period }} days</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        {{ $tld->auto_registrar ? ucfirst($tld->auto_registrar) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        @if($tld->auto_registrar)
                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500"/>
                        @else
                            <x-heroicon-o-x-circle class="w-4 h-4 text-slate-400"/>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $tld->sort_order }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button @click="openEdit({{ json_encode(['id' => $tld->id, 'extension' => $tld->extension, 'register_price' => $tld->register_price ?? '', 'transfer_price' => $tld->transfer_price ?? '', 'renew_price' => $tld->renew_price ?? '', 'grace_period' => $tld->grace_period, 'auto_registrar' => $tld->auto_registrar ?? '', 'sort_order' => $tld->sort_order, 'enabled' => $tld->enabled, 'min_years' => $tld->min_years, 'max_years' => $tld->max_years]) }})"
                                type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </button>
                            <x-confirm-delete :action="route('admin.config.domain-pricing.destroy', $tld)"/>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Add / Edit Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
        <div class="flex items-start justify-center min-h-screen px-4 pt-8 pb-6">
            <div @click="showModal = false" class="fixed inset-0 bg-black/50"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-xl w-full p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white" x-text="editMode ? 'Edit TLD Pricing' : 'Add TLD Pricing'"></h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="editMode ? '/admin/config/domain-pricing/' + form.id : '{{ route('admin.config.domain-pricing.store') }}'" method="POST">
                    @csrf
                    <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">TLD Extension <span class="text-red-500">*</span></label>
                            <input type="text" name="extension" x-model="form.extension" required placeholder=".com"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Register Price ($)</label>
                            <input type="number" name="register_price" x-model="form.register_price" step="0.01" min="0" placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Transfer Price ($)</label>
                            <input type="number" name="transfer_price" x-model="form.transfer_price" step="0.01" min="0" placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Renew Price ($)</label>
                            <input type="number" name="renew_price" x-model="form.renew_price" step="0.01" min="0" placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Grace Period (days)</label>
                            <input type="number" name="grace_period" x-model="form.grace_period" min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Registrar (Auto)</label>
                            <select name="auto_registrar" x-model="form.auto_registrar"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="">None (manual)</option>
                                <option value="enom">eNom</option>
                                <option value="resellerclub">ResellerClub</option>
                                <option value="opensrs">OpenSRS</option>
                                <option value="namecheap">Namecheap</option>
                                <option value="godaddy">GoDaddy</option>
                                <option value="hexonet">HEXONET</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Min Years</label>
                            <input type="number" name="min_years" x-model="form.min_years" min="1"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Years</label>
                            <input type="number" name="max_years" x-model="form.max_years" min="1"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" x-model="form.enabled"
                                class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-slate-700 dark:text-slate-300">Enabled</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            <span x-text="editMode ? 'Save Changes' : 'Add TLD'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
