@extends("admin.layouts.app")
@section("title", $client->full_name)
@section("content")
<x-flash-message/>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
    <div>
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center">
                <span class="text-white text-lg font-bold">{{ strtoupper(substr($client->first_name, 0, 1) . substr($client->last_name, 0, 1)) }}</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $client->full_name }}</h1>
                <p class="text-sm text-slate-500">{{ $client->email }}@if($client->company_name) &middot; {{ $client->company_name }}@endif</p>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.clients.edit', $client) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Edit Client</a>
        <x-status-badge :status="$client->status->value"/>
    </div>
</div>

{{-- Tabs --}}
@php
$tabs = [
    'summary' => ['label' => 'Summary', 'icon' => 'heroicon-o-home'],
    'services' => ['label' => 'Services', 'icon' => 'heroicon-o-server-stack'],
    'domains' => ['label' => 'Domains', 'icon' => 'heroicon-o-globe-alt'],
    'invoices' => ['label' => 'Invoices', 'icon' => 'heroicon-o-document-text'],
    'tickets' => ['label' => 'Tickets', 'icon' => 'heroicon-o-ticket'],
    'notes' => ['label' => 'Notes', 'icon' => 'heroicon-o-pencil-square'],
    'log' => ['label' => 'Activity Log', 'icon' => 'heroicon-o-clock'],
];
@endphp
<div class="border-b border-slate-200 dark:border-slate-700 mb-6">
    <nav class="flex gap-1 overflow-x-auto">
        @foreach($tabs as $key => $t)
        <a href="{{ route('admin.clients.show', ['client' => $client, 'tab' => $key]) }}"
           class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition {{ $tab === $key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
            {{ $t['label'] }}
        </a>
        @endforeach
    </nav>
</div>

{{-- Tab Content --}}
@if($tab === 'summary')
    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <x-stats-card title="Services" :value="$serviceCount" icon="server" color="indigo"/>
        <x-stats-card title="Domains" :value="$domainCount" icon="globe" color="sky"/>
        <x-stats-card title="Invoices" :value="$invoiceCount" icon="document" color="emerald"/>
        <x-stats-card title="Tickets" :value="$ticketCount" icon="ticket" color="amber"/>
        <x-stats-card title="Unpaid" :value="'$' . number_format($unpaidInvoices, 2)" icon="currency" color="red"/>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Profile Card --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">Client Profile</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd class="text-slate-900 dark:text-white">{{ $client->full_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Company</dt><dd class="text-slate-900 dark:text-white">{{ $client->company_name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="text-slate-900 dark:text-white">{{ $client->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="text-slate-900 dark:text-white">{{ $client->phone_number ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Address</dt><dd class="text-slate-900 dark:text-white text-right">{{ $client->address1 ?? '-' }}<br>{{ $client->city }} {{ $client->state }} {{ $client->postcode }}<br>{{ $client->country }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Credit</dt><dd class="font-semibold text-emerald-600">${{ number_format($client->credit, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Created</dt><dd class="text-slate-900 dark:text-white">{{ $client->created_at->format('d M Y') }}</dd></div>
            </dl>
        </div>

        {{-- Recent Invoices --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Invoices</h3>
                <a href="{{ route('admin.clients.show', ['client' => $client, 'tab' => 'invoices']) }}" class="text-xs text-indigo-600 hover:underline">View All</a>
            </div>
            @forelse($recentInvoices as $inv)
            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                <div>
                    <a href="{{ route('admin.invoices.show', $inv) }}" class="text-sm font-medium text-indigo-600 hover:underline">{{ $inv->invoice_num }}</a>
                    <p class="text-xs text-slate-500">{{ $inv->date?->format('d M Y') ?? '-' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium">${{ number_format($inv->total, 2) }}</p>
                    <x-status-badge :status="$inv->status" size="xs"/>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-500">No invoices yet.</p>
            @endforelse
        </div>

        {{-- Recent Tickets --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Tickets</h3>
                <a href="{{ route('admin.clients.show', ['client' => $client, 'tab' => 'tickets']) }}" class="text-xs text-indigo-600 hover:underline">View All</a>
            </div>
            @forelse($recentTickets as $ticket)
            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-sm font-medium text-indigo-600 hover:underline truncate block">{{ $ticket->title }}</a>
                    <p class="text-xs text-slate-500">{{ $ticket->department->name ?? '-' }}</p>
                </div>
                <x-status-badge :status="$ticket->status" size="xs"/>
            </div>
            @empty
            <p class="text-sm text-slate-500">No tickets yet.</p>
            @endforelse
        </div>
    </div>

@elseif($tab === 'services')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        @if($services->isEmpty())
            <x-empty-state title="No services" description="This client has no active services." icon="server"/>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Domain</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Billing</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Next Due</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($services as $service)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3"><a href="{{ route('admin.services.show', $service) }}" class="text-indigo-600 hover:underline font-medium">{{ $service->product->name ?? 'N/A' }}</a></td>
                    <td class="px-4 py-3">{{ $service->domain ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $service->billing_cycle }}</td>
                    <td class="px-4 py-3">${{ number_format($service->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ $service->next_due_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$service->status"/></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $services->appends(['tab' => 'services'])->links() }}</div>
        @endif
    </div>

@elseif($tab === 'domains')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        @if($domains->isEmpty())
            <x-empty-state title="No domains" description="This client has no registered domains." icon="globe"/>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Domain</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Registrar</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Registered</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Expires</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($domains as $domain)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3 font-medium">{{ $domain->domain }}</td>
                    <td class="px-4 py-3">{{ $domain->registrar ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $domain->registration_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $domain->expiry_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$domain->status"/></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $domains->appends(['tab' => 'domains'])->links() }}</div>
        @endif
    </div>

@elseif($tab === 'invoices')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        @if($invoices->isEmpty())
            <x-empty-state title="No invoices" description="No invoices for this client." icon="document"/>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Invoice #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Due Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($invoices as $inv)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3"><a href="{{ route('admin.invoices.show', $inv) }}" class="text-indigo-600 hover:underline font-medium">{{ $inv->invoice_num }}</a></td>
                    <td class="px-4 py-3">{{ $inv->date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $inv->due_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-4 py-3 font-medium">${{ number_format($inv->total, 2) }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$inv->status"/></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $invoices->appends(['tab' => 'invoices'])->links() }}</div>
        @endif
    </div>

@elseif($tab === 'tickets')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        @if($tickets->isEmpty())
            <x-empty-state title="No tickets" description="No support tickets from this client." icon="ticket"/>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Subject</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Last Reply</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($tickets as $ticket)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3 font-mono text-xs">{{ $ticket->tid }}</td>
                    <td class="px-4 py-3">{{ $ticket->department->name ?? '-' }}</td>
                    <td class="px-4 py-3"><a href="{{ route('admin.tickets.show', $ticket) }}" class="text-indigo-600 hover:underline font-medium">{{ $ticket->title }}</a></td>
                    <td class="px-4 py-3"><x-status-badge :status="strtolower($ticket->priority)" size="xs"/></td>
                    <td class="px-4 py-3">{{ $ticket->last_reply?->diffForHumans() ?? '-' }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$ticket->status"/></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $tickets->appends(['tab' => 'tickets'])->links() }}</div>
        @endif
    </div>

@elseif($tab === 'notes')
    <div class="space-y-4">
        {{-- Add Note Form --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Add Note</h3>
            <form method="POST" action="{{ route('admin.clients.notes.store', $client) }}">
                @csrf
                <textarea name="note" rows="3" required placeholder="Type your note here..."
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                <div class="flex items-center justify-between mt-3">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                        <input type="checkbox" name="sticky" value="1" class="rounded border-slate-300"> Sticky note
                    </label>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Add Note</button>
                </div>
            </form>
        </div>
        {{-- Notes list --}}
        @forelse($notes as $note)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border {{ $note->sticky ? 'border-amber-300 dark:border-amber-600' : 'border-slate-200 dark:border-slate-700' }} p-4">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap">{{ $note->note }}</p>
                    <p class="text-xs text-slate-500 mt-2">{{ $note->created_at->format('d M Y H:i') }} @if($note->sticky) <span class="text-amber-600 font-medium">Pinned</span>@endif</p>
                </div>
            </div>
        </div>
        @empty
        <x-empty-state title="No notes" description="Add a note about this client." icon="document"/>
        @endforelse
    </div>

@elseif($tab === 'log')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        @if($logs->isEmpty())
            <x-empty-state title="No activity" description="No activity log entries for this client." icon="inbox"/>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Admin</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($logs as $log)
                <tr>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-3">{{ $log->admin ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $log->action ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $log->description }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $logs->appends(['tab' => 'log'])->links() }}</div>
        @endif
    </div>
@endif

@endsection
