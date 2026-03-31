@extends('admin.layouts.app')
@section('title', 'Ticket Statuses')
@section('content')

<div x-data="{
    showModal: false,
    editMode: false,
    form: {
        id: null, title: '', color: '#6366f1', sort_order: 0,
        show_active: true, show_awaiting: false, auto_close: false
    },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, title: '', color: '#6366f1', sort_order: 0,
            show_active: true, show_awaiting: false, auto_close: false };
        this.showModal = true;
    },
    openEdit(status) {
        this.editMode = true;
        this.form = { ...status };
        this.showModal = true;
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Ticket Statuses</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage ticket lifecycle states and display settings</p>
        </div>
        <button @click="openAdd()" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Status
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($statuses->isEmpty())
            <x-empty-state icon="ticket" title="No ticket statuses" description="Create statuses to track ticket progress through your support workflow."/>
        @else
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Color</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sort Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Show Active</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Show Awaiting</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Auto Close</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($statuses as $status)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $status->title }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full border border-slate-200 dark:border-slate-600 shadow-sm flex-shrink-0"
                                style="background-color: {{ $status->color }}"></div>
                            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $status->color }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $status->sort_order }}</td>
                    <td class="px-6 py-4">
                        @if($status->show_active)
                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500"/>
                        @else
                            <x-heroicon-o-minus-circle class="w-4 h-4 text-slate-300 dark:text-slate-600"/>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($status->show_awaiting)
                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500"/>
                        @else
                            <x-heroicon-o-minus-circle class="w-4 h-4 text-slate-300 dark:text-slate-600"/>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($status->auto_close)
                            <x-heroicon-s-clock class="w-4 h-4 text-amber-500"/>
                        @else
                            <x-heroicon-o-minus-circle class="w-4 h-4 text-slate-300 dark:text-slate-600"/>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button @click="openEdit({{ json_encode(['id' => $status->id, 'title' => $status->title, 'color' => $status->color, 'sort_order' => $status->sort_order, 'show_active' => (bool)$status->show_active, 'show_awaiting' => (bool)$status->show_awaiting, 'auto_close' => (bool)$status->auto_close]) }})"
                                type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </button>
                            <x-confirm-delete :action="route('admin.config.ticket-statuses.destroy', $status)"/>
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
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showModal = false" class="fixed inset-0 bg-black/50"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white" x-text="editMode ? 'Edit Status' : 'Add Status'"></h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="editMode ? '/admin/config/ticket-statuses/' + form.id : '{{ route('admin.config.ticket-statuses.store') }}'" method="POST">
                    @csrf
                    <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" x-model="form.title" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="color" x-model="form.color"
                                    class="h-10 w-16 rounded border border-slate-300 dark:border-slate-600 cursor-pointer bg-white dark:bg-slate-700">
                                <input type="text" x-model="form.color" placeholder="#6366f1"
                                    class="flex-1 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-3 gap-4 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="show_active" value="0">
                                <input type="checkbox" name="show_active" value="1" x-model="form.show_active"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Show Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="show_awaiting" value="0">
                                <input type="checkbox" name="show_awaiting" value="1" x-model="form.show_awaiting"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Show Awaiting</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="auto_close" value="0">
                                <input type="checkbox" name="auto_close" value="1" x-model="form.auto_close"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Auto Close</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            <span x-text="editMode ? 'Save Changes' : 'Add Status'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
