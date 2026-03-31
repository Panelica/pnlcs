@extends('admin.layouts.app')
@section('title', 'Server Groups')
@section('content')

<div x-data="{
    showModal: false,
    editMode: false,
    form: { id: null, name: '', fill_type: 'fill' },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, name: '', fill_type: 'fill' };
        this.showModal = true;
    },
    openEdit(group) {
        this.editMode = true;
        this.form = { ...group };
        this.showModal = true;
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Server Groups</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Group servers for load balancing and overflow distribution</p>
        </div>
        <button @click="openAdd()" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Group
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($serverGroups->isEmpty())
            <x-empty-state icon="server" title="No server groups" description="Create groups to distribute accounts across multiple servers."/>
        @else
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fill Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Servers</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($serverGroups as $group)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $group->name }}</td>
                    <td class="px-6 py-4">
                        @if($group->fill_type === 'round_robin')
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Round Robin</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Fill</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        <span class="font-medium">{{ $group->servers_count ?? $group->servers->count() }}</span> server(s)
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button @click="openEdit({{ json_encode(['id' => $group->id, 'name' => $group->name, 'fill_type' => $group->fill_type]) }})"
                                type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </button>
                            <x-confirm-delete :action="route('admin.config.server-groups.destroy', $group)"/>
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
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white" x-text="editMode ? 'Edit Server Group' : 'Add Server Group'"></h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="editMode ? '/admin/config/server-groups/' + form.id : '{{ route('admin.config.server-groups.store') }}'" method="POST">
                    @csrf
                    <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="form.name" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fill Type</label>
                            <select name="fill_type" x-model="form.fill_type"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="fill">Fill — fill one server before moving to next</option>
                                <option value="round_robin">Round Robin — distribute evenly</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            <span x-text="editMode ? 'Save Changes' : 'Create Group'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
