@extends('admin.layouts.app')
@section('title', 'Ticket Departments')
@section('content')

<div x-data="{
    showModal: false,
    editMode: false,
    form: {
        id: null, name: '', description: '', email: '',
        clients_only: false, hidden: false, sort_order: 0, feedback_request: false
    },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, name: '', description: '', email: '',
            clients_only: false, hidden: false, sort_order: 0, feedback_request: false };
        this.showModal = true;
    },
    openEdit(dept) {
        this.editMode = true;
        this.form = { ...dept };
        this.showModal = true;
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Ticket Departments</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Organize support tickets by department</p>
        </div>
        <button @click="openAdd()" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Department
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($departments->isEmpty())
            <x-empty-state icon="ticket" title="No departments" description="Create departments to route support tickets to the right team."/>
        @else
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Clients Only</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hidden</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sort</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($departments as $dept)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $dept->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $dept->email ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 max-w-xs truncate">{{ $dept->description ?: '—' }}</td>
                    <td class="px-6 py-4">
                        @if($dept->clients_only)
                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500"/>
                        @else
                            <x-heroicon-o-minus-circle class="w-4 h-4 text-slate-300 dark:text-slate-600"/>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($dept->hidden)
                            <x-heroicon-s-eye-slash class="w-4 h-4 text-amber-500"/>
                        @else
                            <x-heroicon-o-eye class="w-4 h-4 text-slate-300 dark:text-slate-600"/>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $dept->sort_order }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button @click="openEdit({{ json_encode(['id' => $dept->id, 'name' => $dept->name, 'description' => $dept->description ?? '', 'email' => $dept->email ?? '', 'clients_only' => (bool)$dept->clients_only, 'hidden' => (bool)$dept->hidden, 'sort_order' => $dept->sort_order, 'feedback_request' => (bool)$dept->feedback_request]) }})"
                                type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </button>
                            <x-confirm-delete :action="route('admin.config.ticket-departments.destroy', $dept)"/>
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
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white" x-text="editMode ? 'Edit Department' : 'Add Department'"></h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="editMode ? '/admin/config/ticket-departments/' + form.id : '{{ route('admin.config.ticket-departments.store') }}'" method="POST">
                    @csrf
                    <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="form.name" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                            <textarea name="description" x-model="form.description" rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                            <input type="email" name="email" x-model="form.email" placeholder="support@yourdomain.com"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-3 gap-4 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="clients_only" value="0">
                                <input type="checkbox" name="clients_only" value="1" x-model="form.clients_only"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Clients Only</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="hidden" value="0">
                                <input type="checkbox" name="hidden" value="1" x-model="form.hidden"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Hidden</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="feedback_request" value="0">
                                <input type="checkbox" name="feedback_request" value="1" x-model="form.feedback_request"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Send Feedback</span>
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
                            <span x-text="editMode ? 'Save Changes' : 'Create Department'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
