@extends("admin.layouts.app")
@section("title", "Staff Management")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Staff Management</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage administrator accounts and access</p>
    </div>
    <button type="button"
        x-data
        @click="$dispatch('open-modal-add-admin')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Admin
    </button>
</div>

@if($admins->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No admins found" description="Add your first administrator to get started." icon="users"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Name</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Username / Email</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Role</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Last Login</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Status</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($admins as $admin)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition" x-data="{ editing: false }">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-medium text-xs">
                            {{ strtoupper(substr($admin->first_name, 0, 1)) }}{{ strtoupper(substr($admin->last_name, 0, 1)) }}
                        </div>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $admin->full_name }}</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-slate-900 dark:text-white">{{ $admin->username }}</div>
                    <div class="text-slate-500 dark:text-slate-400 text-xs">{{ $admin->email }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {{ $admin->role?->name ?? '—' }}
                    @if($admin->role?->is_full_admin)
                        <span class="ml-1 px-1.5 py-0.5 text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded">Full</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">
                    {{ $admin->last_login ? $admin->last_login->diffForHumans() : 'Never' }}
                </td>
                <td class="px-4 py-3">
                    @if($admin->is_disabled)
                        <x-status-badge status="disabled" label="Disabled"/>
                    @else
                        <x-status-badge status="active" label="Active"/>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                            @click="$dispatch('open-modal-edit-admin-{{ $admin->id }}')"
                            class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4"/>
                        </button>
                        @if($admin->id !== auth('admin')->id())
                        <x-confirm-delete :action="route('admin.config.admins.destroy', $admin)"/>
                        @endif
                    </div>
                </td>
            </tr>

            {{-- Edit Modal for each admin --}}
            <x-modal :name="'edit-admin-' . $admin->id" title="Edit Admin" maxWidth="lg">
                <form method="POST" action="{{ route('admin.config.admins.update', $admin) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">First Name</label>
                            <input type="text" name="first_name" value="{{ $admin->first_name }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Last Name</label>
                            <input type="text" name="last_name" value="{{ $admin->last_name }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                            <input type="text" name="username" value="{{ $admin->username }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                            <input type="email" name="email" value="{{ $admin->email }}" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Role</label>
                            <select name="role_id" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected($admin->role_id === $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">New Password <span class="text-slate-400">(leave blank to keep)</span></label>
                            <input type="password" name="password" minlength="6"
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button type="button" @click="$dispatch('close-modal-edit-admin-{{ $admin->id }}')"
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

{{-- Add Admin Modal --}}
<x-modal name="add-admin" title="Add New Admin" maxWidth="lg">
    <form method="POST" action="{{ route('admin.config.admins.store') }}">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">First Name</label>
                <input type="text" name="first_name" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Last Name</label>
                <input type="text" name="last_name" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                <input type="text" name="username" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                <input type="email" name="email" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                <input type="password" name="password" minlength="6" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Role</label>
                <select name="role_id" required
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">-- Select Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-admin')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Create Admin</button>
        </div>
    </form>
</x-modal>

@endsection
