@extends('admin.layouts.app')
@section('title', 'Billable Items')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Billable Items</h1>
    <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-billable'))"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Billable Item
    </button>
</div>

<x-flash-message/>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($items->isEmpty())
        <x-empty-state title="No billable items" description="Add one-off or recurring charges to invoice clients." icon="currency"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Due Date</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Invoiced</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ $item->description }}</td>
                        <td class="px-6 py-4">
                            @if($item->client)
                                <p class="text-slate-700 dark:text-slate-300">{{ $item->client->first_name }} {{ $item->client->last_name }}</p>
                                <p class="text-xs text-slate-400">{{ $item->client->email }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">${{ number_format($item->amount, 2) }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                            {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('M d, Y') : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($item->invoice_id)
                                <x-status-badge status="active"/>
                            @else
                                <x-status-badge status="pending"/>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end">
                                <x-confirm-delete action="{{ route('admin.config.billable-items.destroy', $item) }}"/>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $items->links() }}
        </div>
        @endif
    @endif
</div>

{{-- Add Modal --}}
<x-modal name="add-billable" title="Add Billable Item" max-width="xl">
    <form method="POST" action="{{ route('admin.config.billable-items.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Client</label>
                <input type="number" name="client_id" required placeholder="Client ID" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-slate-400 mt-1">Enter the numeric client ID.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                <input type="text" name="description" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-slate-400">$</span>
                        <input type="number" name="amount" step="0.01" min="0" required class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="mark_invoiced" value="1" id="mark_invoiced" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                <label for="mark_invoiced" class="text-sm text-slate-700 dark:text-slate-300">Mark as already invoiced</label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-billable'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Add Item</button>
        </div>
    </form>
</x-modal>
@endsection
