@extends("admin.layouts.app")
@section("title", "API Credentials")
@section("content")

<x-flash-message/>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">API Credentials</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage API keys for external integrations</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal-add-credential')"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Generate Credential
    </button>
</div>

@if($credentials->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <x-empty-state title="No API credentials" description="Generate API credentials to enable external access." icon="shield"/>
    </div>
@else
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Identifier</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Description</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Owner</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Created</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Status</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($credentials as $cred)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                <td class="px-4 py-3">
                    <code class="font-mono text-xs bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-2 py-1 rounded">
                        {{ substr($cred->identifier, 0, 8) }}...{{ substr($cred->identifier, -4) }}
                    </code>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $cred->description ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cred->admin?->full_name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $cred->created_at->format('M d, Y') }}</td>
                <td class="px-4 py-3">
                    @if($cred->active)
                        <x-status-badge status="active" label="Active"/>
                    @else
                        <x-status-badge status="disabled" label="Inactive"/>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end">
                        <x-confirm-delete :action="route('admin.config.api-credentials.destroy', $cred)"
                            message="Revoke this credential?"
                            buttonClass="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition">
                            <span class="flex items-center gap-1 text-xs">
                                <x-heroicon-o-x-circle class="w-4 h-4"/>
                                Revoke
                            </span>
                        </x-confirm-delete>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<x-modal name="add-credential" title="Generate API Credential" maxWidth="md">
    <form method="POST" action="{{ route('admin.config.api-credentials.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Description <span class="text-slate-400">(optional)</span></label>
                <input type="text" name="description" placeholder="e.g. WHMCS Integration, Mobile App"
                    class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex items-start gap-2">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0"/>
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        The secret key will be displayed only once after generation. Store it securely.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" @click="$dispatch('close-modal-add-credential')"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">Generate</button>
        </div>
    </form>
</x-modal>

@endsection
