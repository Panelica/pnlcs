@extends("admin.layouts.app")
@section("title", "Clients")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Clients</h1>
    <a href="{{ route("admin.clients.create") }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
        <x-heroicon-s-plus class="w-4 h-4" /> Add Client
    </a>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request("search") }}" placeholder="Name, email, company..." class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">
                <option value="">All</option>
                <option value="active" {{ request("status") == "active" ? "selected" : "" }}>Active</option>
                <option value="inactive" {{ request("status") == "inactive" ? "selected" : "" }}>Inactive</option>
                <option value="closed" {{ request("status") == "closed" ? "selected" : "" }}>Closed</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Group</label>
            <select name="group_id" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">
                <option value="">All Groups</option>
                @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ request("group_id") == $group->id ? "selected" : "" }}>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-sm font-medium rounded-lg transition-colors">Filter</button>
    </form>
</div>

<!-- Table -->
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">ID</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Name</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Email</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Company</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Status</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Created</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($clients as $client)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-4 py-3 font-mono text-xs">{{ $client->id }}</td>
                <td class="px-4 py-3 font-medium"><a href="{{ route("admin.clients.show", $client) }}" class="text-indigo-600 hover:text-indigo-500">{{ $client->full_name }}</a></td>
                <td class="px-4 py-3">{{ $client->email }}</td>
                <td class="px-4 py-3">{{ $client->company_name ?? "-" }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $client->status->value == "active" ? "bg-emerald-100 text-emerald-700" : ($client->status->value == "inactive" ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-700") }}">
                        {{ ucfirst($client->status->value) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $client->created_at->format("d M Y") }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route("admin.clients.edit", $client) }}" class="text-slate-400 hover:text-indigo-600"><x-heroicon-o-pencil-square class="w-4 h-4 inline" /></a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $clients->withQueryString()->links() }}</div>
</div>
@endsection
