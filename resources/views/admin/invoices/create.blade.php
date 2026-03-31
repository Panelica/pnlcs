@extends("admin.layouts.app")
@section("title", "Create Invoice")
@section("content")
<div class="max-w-4xl" x-data="invoiceBuilder()">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Create Invoice</h1>
            <p class="text-slate-500 text-sm mt-1">Manually create an invoice for a client</p>
        </div>
        <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            Back to Invoices
        </a>
    </div>

    @if($errors->any())
    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.invoices.store') }}">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: main form --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Client selector --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500 mb-4">Client</h2>
                    <div>
                        <label for="client_id" class="block text-sm font-medium mb-1">Select Client <span class="text-red-500">*</span></label>
                        <select name="client_id" id="client_id" required
                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">— Choose a client —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $selectedClient?->id) == $client->id ? 'selected' : '' }}>
                                {{ $client->display_name }} ({{ $client->email }})
                            </option>
                            @endforeach
                        </select>
                        @error('client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Line items --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500">Line Items</h2>
                        <button type="button" @click="addItem()"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Item
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex gap-3 items-start p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                <div class="flex-1">
                                    <input type="text"
                                           :name="`items[${index}][description]`"
                                           x-model="item.description"
                                           placeholder="Description"
                                           required
                                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="w-32">
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                                        <input type="number"
                                               :name="`items[${index}][amount]`"
                                               x-model.number="item.amount"
                                               @input="recalculate()"
                                               placeholder="0.00"
                                               step="0.01"
                                               min="0"
                                               required
                                               class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 pt-2">
                                    <input type="checkbox"
                                           :name="`items[${index}][taxed]`"
                                           :value="1"
                                           x-model="item.taxed"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                           :id="`taxed_${index}`">
                                    <label :for="`taxed_${index}`" class="text-xs text-slate-500 cursor-pointer select-none">Tax</label>
                                </div>
                                <button type="button" @click="removeItem(index)"
                                        x-show="items.length > 1"
                                        class="pt-1.5 text-red-400 hover:text-red-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    @error('items') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('items.*.description') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('items.*.amount') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Notes --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500 mb-4">Notes</h2>
                    <textarea name="notes" rows="3" placeholder="Optional notes visible on the invoice…"
                              class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('notes') }}</textarea>
                </div>

            </div>

            {{-- Right: summary + dates --}}
            <div class="space-y-6">

                {{-- Dates --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500 mb-4">Dates</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="date" class="block text-sm font-medium mb-1">Invoice Date <span class="text-red-500">*</span></label>
                            <input type="date" name="date" id="date" required
                                   value="{{ old('date', now()->toDateString()) }}"
                                   class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="due_date" class="block text-sm font-medium mb-1">Due Date <span class="text-red-500">*</span></label>
                            <input type="date" name="due_date" id="due_date" required
                                   value="{{ old('due_date', now()->addDays(14)->toDateString()) }}"
                                   class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('due_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Payment method --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500 mb-4">Payment</h2>
                    <div>
                        <label for="payment_method" class="block text-sm font-medium mb-1">Payment Method</label>
                        <select name="payment_method" id="payment_method"
                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach($paymentMethods as $pm)
                            <option value="{{ $pm->gateway_name }}" {{ old('payment_method') == $pm->gateway_name ? 'selected' : '' }}>
                                {{ $pm->description }}
                            </option>
                            @endforeach
                            <option value="banktransfer" {{ old('payment_method') == 'banktransfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="manual" {{ old('payment_method') == 'manual' ? 'selected' : '' }}>Manual</option>
                        </select>
                    </div>
                </div>

                {{-- Totals summary --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="font-semibold text-sm uppercase tracking-wider text-slate-500 mb-4">Summary</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Subtotal</dt>
                            <dd class="font-medium" x-text="'$' + subtotal.toFixed(2)">$0.00</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 mt-2">
                            <dt class="font-bold">Estimated Total</dt>
                            <dd class="font-bold text-lg" x-text="'$' + subtotal.toFixed(2)">$0.00</dd>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-400 mt-2">Taxes will be calculated on save based on client location.</p>
                </div>

                <button type="submit"
                        class="w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors">
                    Create Invoice
                </button>
            </div>

        </div>
    </form>
</div>

<script>
function invoiceBuilder() {
    return {
        items: [
            { description: '', amount: 0, taxed: true }
        ],
        subtotal: 0,

        addItem() {
            this.items.push({ description: '', amount: 0, taxed: true });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
                this.recalculate();
            }
        },

        recalculate() {
            this.subtotal = this.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
        }
    };
}
</script>
@endsection
