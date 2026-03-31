@extends("client.layouts.app")
@section("title", "Support Tickets")
@section("content")
<div class="flex justify-between items-center mb-6"><h1 class="text-2xl font-bold">Support Tickets</h1><a href="{{ route("client.tickets.create") }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg">Open Ticket</a></div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-4 py-3 text-left">Ticket #</th><th class="px-4 py-3 text-left">Department</th><th class="px-4 py-3 text-left">Subject</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Last Reply</th></tr></thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($tickets as $t)
            <tr><td class="px-4 py-3 font-mono text-xs"><a href="{{ route("client.tickets.show", $t) }}" class="text-indigo-600">#{{ $t->tid }}</a></td><td class="px-4 py-3">{{ $t->department->name ?? "" }}</td><td class="px-4 py-3 font-medium"><a href="{{ route("client.tickets.show", $t) }}" class="text-indigo-600">{{ Str::limit($t->title, 50) }}</a></td><td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full {{ $t->status == "open" ? "bg-emerald-100 text-emerald-700" : ($t->status == "answered" ? "bg-blue-100 text-blue-700" : "bg-slate-100 text-slate-700") }}">{{ ucfirst($t->status) }}</span></td><td class="px-4 py-3 text-xs text-slate-400">{{ $t->last_reply?->diffForHumans() }}</td></tr>
            @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">No tickets. <a href="{{ route("client.tickets.create") }}" class="text-indigo-600">Open one</a></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
