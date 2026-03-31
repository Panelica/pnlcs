@extends('admin.layouts.app')
@section('title', 'Servers')
@section('content')

<div x-data="{
    showModal: false,
    editMode: false,
    form: {
        id: null, name: '', hostname: '', ip_address: '', port: 2222, type: 'custom',
        username: '', password: '', max_accounts: 100,
        nameserver1: '', nameserver2: '', nameserver3: '', nameserver4: '', nameserver5: '',
        active: true
    },
    testResult: null,
    testLoading: false,
    openAdd() {
        this.editMode = false;
        this.testResult = null;
        this.form = { id: null, name: '', hostname: '', ip_address: '', port: 2222, type: 'custom',
            username: '', password: '', max_accounts: 100,
            nameserver1: '', nameserver2: '', nameserver3: '', nameserver4: '', nameserver5: '', active: true };
        this.showModal = true;
    },
    openEdit(server) {
        this.editMode = true;
        this.testResult = null;
        this.form = { ...server };
        this.showModal = true;
    },
    testConnection() {
        if (!this.form.id) return;
        this.testLoading = true;
        this.testResult = null;
        fetch('/admin/config/servers/' + this.form.id + '/test', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => { this.testResult = data; })
        .catch(() => { this.testResult = { success: false, message: 'Request failed' }; })
        .finally(() => { this.testLoading = false; });
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Servers</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage hosting servers and provisioning configuration</p>
        </div>
        <button @click="openAdd()" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <x-heroicon-s-plus class="w-4 h-4"/>
            Add Server
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($servers->isEmpty())
            <x-empty-state icon="server" title="No servers configured"
                description="Add your first hosting server to start provisioning accounts."/>
        @else
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hostname</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Max Accounts</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Groups</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($servers as $server)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $server->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 font-mono">{{ $server->hostname }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 font-mono">{{ $server->ip_address ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 uppercase">{{ $server->type ?? 'custom' }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        {{ $server->max_accounts == 0 ? 'Unlimited' : number_format($server->max_accounts) }}
                    </td>
                    <td class="px-6 py-4">
                        @if($server->disabled)
                            <x-status-badge status="suspended"/>
                        @elseif($server->active)
                            <x-status-badge status="active"/>
                        @else
                            <x-status-badge status="cancelled"/>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        @if($server->groups->count())
                            <div class="flex flex-wrap gap-1">
                                @foreach($server->groups as $group)
                                    <span class="px-2 py-0.5 text-xs rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">{{ $group->name }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button @click="openEdit({{ json_encode(['id' => $server->id, 'name' => $server->name, 'hostname' => $server->hostname, 'ip_address' => $server->ip_address, 'port' => $server->port, 'type' => $server->type ?? 'custom', 'username' => $server->username, 'password' => '', 'max_accounts' => $server->max_accounts, 'nameserver1' => $server->nameserver1, 'nameserver2' => $server->nameserver2, 'nameserver3' => $server->nameserver3, 'nameserver4' => $server->nameserver4, 'nameserver5' => $server->nameserver5, 'active' => $server->active]) }})"
                                type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-pencil-square class="w-4 h-4"/>
                            </button>
                            <x-confirm-delete :action="route('admin.config.servers.destroy', $server)"/>
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
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white" x-text="editMode ? 'Edit Server' : 'Add Server'"></h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="editMode ? '/admin/config/servers/' + form.id : '{{ route('admin.config.servers.store') }}'" method="POST">
                    @csrf
                    <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="form.name" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                            <select name="type" x-model="form.type"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="custom">Custom</option>
                                <option value="cpanel">cPanel</option>
                                <option value="plesk">Plesk</option>
                                <option value="directadmin">DirectAdmin</option>
                                <option value="panelica">Panelica</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Hostname <span class="text-red-500">*</span></label>
                            <input type="text" name="hostname" x-model="form.hostname" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">IP Address</label>
                            <input type="text" name="ip_address" x-model="form.ip_address"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Port</label>
                            <input type="number" name="port" x-model="form.port"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Accounts <span class="text-xs text-slate-400">(0 = unlimited)</span></label>
                            <input type="number" name="max_accounts" x-model="form.max_accounts" min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                            <input type="text" name="username" x-model="form.username"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                            <input type="password" name="password" x-model="form.password" autocomplete="new-password"
                                :placeholder="editMode ? 'Leave blank to keep current' : ''"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nameserver 1</label>
                            <input type="text" name="nameserver1" x-model="form.nameserver1"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nameserver 2</label>
                            <input type="text" name="nameserver2" x-model="form.nameserver2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nameserver 3</label>
                            <input type="text" name="nameserver3" x-model="form.nameserver3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nameserver 4</label>
                            <input type="text" name="nameserver4" x-model="form.nameserver4"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nameserver 5</label>
                            <input type="text" name="nameserver5" x-model="form.nameserver5"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" x-model="form.active"
                                class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                        </label>
                    </div>

                    <div x-show="editMode && testResult !== null" x-transition class="mt-4 p-3 rounded-lg text-sm"
                        :class="testResult && testResult.success
                            ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                            : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800'">
                        <span x-text="testResult ? testResult.message : ''"></span>
                    </div>

                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button x-show="editMode" @click.prevent="testConnection()" type="button"
                            :disabled="testLoading"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition disabled:opacity-50">
                            <x-heroicon-o-signal class="w-4 h-4"/>
                            <span x-text="testLoading ? 'Testing...' : 'Test Connection'"></span>
                        </button>
                        <div class="flex items-center gap-3 ml-auto">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                                <span x-text="editMode ? 'Save Changes' : 'Add Server'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
