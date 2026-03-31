@extends("admin.layouts.app")
@section("title", "Support Tickets")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Support Tickets</h1>
</div>

<div class="flex gap-2 mb-6 flex-wrap">
    @foreach(["" => "All", "open" => "Open", "answered" => "Answered", "customer-reply" => "Customer Reply", "on hold" => "On Hold", "in progress" => "In Progress", "closed" => "Closed"] as $val => $label)
    <a href="{{ route("admin.tickets.index", ["status" => $val]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request("status") == $val ? "bg-indigo-600 text-white" : "bg-slate-100 dark:bg-slate-700 hover:bg-slate-200" }} transition-colors">{{ $label }}</a>
    @endforeach
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Ticket</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Department</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Subject</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Client</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Priority</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Last Reply</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($tickets as $ticket)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 font-mono text-xs"><a href="{{ route("admin.tickets.show", $ticket) }}" class="text-indigo-600">#{{ $ticket->tid }}</a></td>
                <td class="px-4 py-3">{{ $ticket->department->name ?? "N/A" }}</td>
                <td class="px-4 py-3 font-medium"><a href="{{ route("admin.tickets.show", $ticket) }}" class="text-indigo-600 hover:text-indigo-500">{{ Str::limit($ticket->title, 50) }}</a></td>
                <td class="px-4 py-3">{{ $ticket->client->full_name ?? $ticket->name ?? $ticket->email }}</td>
                <td class="px-4 py-3">
                    @php $pc = ["low" => "slate", "medium" => "blue", "high" => "amber", "critical" => "red"]; @endphp
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $pc[$ticket->priority] ?? "slate" }}-100 text-{{ $pc[$ticket->priority] ?? "slate" }}-700">{{ ucfirst($ticket->priority) }}</span>
                </td>
                <td class="px-4 py-3">
                    @php $sc = ["open" => "emerald", "answered" => "blue", "customer-reply" => "amber", "on hold" => "violet", "in progress" => "cyan", "closed" => "slate"]; @endphp
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $sc[$ticket->status] ?? "slate" }}-100 text-{{ $sc[$ticket->status] ?? "slate" }}-700">{{ ucfirst($ticket->status) }}</span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ $ticket->last_reply?->diffForHumans() ?? "-" }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No tickets found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $tickets->withQueryString()->links() }}</div>
</div>
@endsection
