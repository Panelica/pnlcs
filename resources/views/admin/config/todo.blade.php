@extends('admin.layouts.app')
@section('title', 'To-Do List')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">To-Do List</h1>
    <button onclick="window.dispatchEvent(new CustomEvent('open-modal-add-todo'))"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
        <x-heroicon-s-plus class="w-4 h-4"/>
        Add Task
    </button>
</div>

<x-flash-message/>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($items->isEmpty())
        <x-empty-state title="No tasks yet" description="Add your first to-do item to track admin tasks." icon="document"/>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Due Date</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Admin</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ $item->title }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ Str::limit($item->description, 50) ?: '—' }}</td>
                        <td class="px-6 py-4">
                            <x-status-badge status="{{ strtolower($item->status ?? 'new') }}"/>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                            @if($item->due_date)
                                @php $due = \Carbon\Carbon::parse($item->due_date); @endphp
                                <span class="{{ $due->isPast() && strtolower($item->status) !== 'completed' ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                    {{ $due->format('M d, Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $item->admin ?: '—' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openEditTodo({{ $item->id }}, '{{ addslashes($item->title) }}', {{ json_encode($item->description) }}, '{{ $item->status }}', '{{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('Y-m-d') : '' }}', '{{ $item->admin }}')"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    <x-heroicon-o-pencil class="w-4 h-4"/>
                                </button>
                                <x-confirm-delete action="{{ route('admin.config.todo.destroy', $item) }}"/>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Add Modal --}}
<x-modal name="add-todo" title="New Task" max-width="xl">
    <form method="POST" action="{{ route('admin.config.todo.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                        <option value="New">New</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Assigned Admin</label>
                <input type="text" name="admin" placeholder="Admin name or email" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal-add-todo'))" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Create Task</button>
        </div>
    </form>
</x-modal>

{{-- Edit Modal --}}
<div x-data="{ open: false, id: null, title: '', description: '', status: 'New', due_date: '', admin: '' }"
     x-on:open-edit-todo.window="open = true; id = $event.detail.id; title = $event.detail.title; description = $event.detail.description; status = $event.detail.status; due_date = $event.detail.due_date; admin = $event.detail.admin"
     x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="open" class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-xl w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Task</h3>
                <button x-on:click="open = false" class="text-slate-400 hover:text-slate-600"><x-heroicon-o-x-mark class="w-5 h-5"/></button>
            </div>
            <form method="POST" x-bind:action="'{{ url('admin/config/todo') }}/' + id">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" x-model="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                        <textarea name="description" rows="3" x-model="description" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                            <select name="status" x-model="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                                <option value="New">New</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date</label>
                            <input type="date" name="due_date" x-model="due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Assigned Admin</label>
                        <input type="text" name="admin" x-model="admin" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" x-on:click="open = false" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditTodo(id, title, description, status, due_date, admin) {
    window.dispatchEvent(new CustomEvent('open-edit-todo', { detail: { id, title, description, status: status || 'New', due_date: due_date || '', admin: admin || '' } }));
}
</script>
@endsection
