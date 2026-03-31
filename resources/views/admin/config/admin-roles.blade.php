@extends("admin.layouts.app")
@section("title", "Admin Roles")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Admin Roles</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Define permission sets for staff accounts</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal-add-role')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Role
    </button>
</div>

@if($roles->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No roles found" description="Create your first admin role to assign permissions." icon="shield"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Name</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Description</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Admins</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Full Admin?</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($roles as $role)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $role->name }}</td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $role->description ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-full text-xs font-medium">
                        {{ $role->admins_count }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if($role->is_full_admin)
                        <x-status-badge status="active" label="Yes"/>
                    @else
                        <x-status-badge status="disabled" label="No"/>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                            x-data @click="$dispatch('open-modal-edit-role-{{ $role->id }}')"
                            class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4"/>
                        </button>
                        @if($role->admins_count === 0)
                        <x-confirm-delete :action="route('admin.config.admin-roles.destroy', $role)"
                            message="Delete this role permanently?"/>
                        @else
                        <span class="text-slate-300 dark:text-slate-600 cursor-not-allowed" title="Cannot delete: role has admins">
                            <x-heroicon-o-trash class="w-4 h-4"/>
                        </span>
                        @endif
                    </div>
                </td>
            </tr>

            <x-modal :name="'edit-role-' . $role->id" title="Edit Role" maxWidth="md">
                <form method="POST" action="{{ route('admin.config.admin-roles.update', $role) }}">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Role Name</label>
                            <input type="text" name="name" value="{{ $role->name }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                            <input type="text" name="description" value="{{ $role->description }}"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="is_full_admin" id="is_full_admin_{{ $role->id }}" value="1"
                                @checked($role->is_full_admin)
                                class="w-4 h-4 text-indigo-600 rounded border-slate-300 dark:border-slate-600 focus:ring-indigo-500"/>
                            <label for="is_full_admin_{{ $role->id }}" class="text-sm text-slate-700 dark:text-slate-300">
                                Full Administrator (bypass all permission checks)
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button type="button" @click="$dispatch('close-modal-edit-role-{{ $role->id }}')"
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

<x-modal name="add-role" title="Add New Role" maxWidth="md">
    <form method="POST" action="{{ route('admin.config.admin-roles.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Role Name</label>
                <input type="text" name="name" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                <input type="text" name="description"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_full_admin" id="is_full_admin_new" value="1"
                    class="w-4 h-4 text-indigo-600 rounded border-slate-300 dark:border-slate-600 focus:ring-indigo-500"/>
                <label for="is_full_admin_new" class="text-sm text-slate-700 dark:text-slate-300">
                    Full Administrator (bypass all permission checks)
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-role')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Create Role</button>
        </div>
    </form>
</x-modal>

@endsection
