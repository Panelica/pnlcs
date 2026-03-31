@extends('client.layouts.app')
@section('title', 'My Account')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Welcome, {{ auth()->user()->first_name }}</h1>
    <div class="flex gap-2">
        <a href="{{ route('client.tickets.create') }}" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">Open Ticket</a>
        <a href="{{ route('client.domains.index') }}" class="px-3 py-1.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-50 transition-colors">Register Domain</a>
    </div>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <a href="{{ route('client.services.index') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
        <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
        <p class="text-2xl font-bold">{{ $serviceCount }}</p>
        <p class="text-xs text-slate-500 mt-1">Active Services</p>
    </a>
    <a href="{{ route('client.domains.index') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-emerald-300 dark:hover:border-emerald-600 transition-colors">
        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
        </div>
        <p class="text-2xl font-bold">{{ $domainCount }}</p>
        <p class="text-xs text-slate-500 mt-1">Active Domains</p>
    </a>
    <a href="{{ route('client.invoices.index') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-amber-300 dark:hover:border-amber-600 transition-colors">
        <div class="w-10 h-10 {{ $unpaidInvoices > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-slate-100 dark:bg-slate-700' }} rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 {{ $unpaidInvoices > 0 ? 'text-amber-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <p class="text-2xl font-bold {{ $unpaidInvoices > 0 ? 'text-amber-600' : '' }}">{{ $unpaidInvoices }}</p>
        <p class="text-xs text-slate-500 mt-1">Unpaid Invoices</p>
    </a>
    <a href="{{ route('client.tickets.index') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 text-center hover:border-rose-300 dark:hover:border-rose-600 transition-colors">
        <div class="w-10 h-10 {{ $openTickets > 0 ? 'bg-rose-100 dark:bg-rose-900/30' : 'bg-slate-100 dark:bg-slate-700' }} rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 {{ $openTickets > 0 ? 'text-rose-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        </div>
        <p class="text-2xl font-bold {{ $openTickets > 0 ? 'text-rose-600' : '' }}">{{ $openTickets }}</p>
        <p class="text-xs text-slate-500 mt-1">Open Tickets</p>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Recent Invoices --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-semibold">Recent Invoices</h3>
            <a href="{{ route('client.invoices.index') }}" class="text-xs text-indigo-600 hover:text-indigo-500">View all</a>
        </div>
        @if($recentInvoices->isEmpty())
            <div class="p-8 text-center text-sm text-slate-400">No invoices yet.</div>
        @else
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($recentInvoices as $invoice)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('client.invoices.show', $invoice) }}" class="text-indigo-600 font-medium">#{{ $invoice->invoice_num ?? $invoice->id }}</a></td>
                        <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium">${{ number_format($invoice->total, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ $invoice->status === 'Paid' ? 'bg-emerald-100 text-emerald-700' :
                                   ($invoice->status === 'Unpaid' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                {{ $invoice->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Recent Tickets --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-semibold">Recent Tickets</h3>
            <a href="{{ route('client.tickets.index') }}" class="text-xs text-indigo-600 hover:text-indigo-500">View all</a>
        </div>
        @if($recentTickets->isEmpty())
            <div class="p-8 text-center text-sm text-slate-400">No tickets yet.</div>
        @else
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($recentTickets as $ticket)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('client.tickets.show', $ticket) }}" class="text-indigo-600 font-medium">{{ Str::limit($ticket->title, 30) }}</a></td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $ticket->last_reply?->diffForHumans() ?? $ticket->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ $ticket->status === 'Open' ? 'bg-emerald-100 text-emerald-700' :
                                   ($ticket->status === 'Answered' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Active Services --}}
@if($activeServices->isNotEmpty())
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold">Active Services</h3>
        <a href="{{ route('client.services.index') }}" class="text-xs text-indigo-600 hover:text-indigo-500">View all</a>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($activeServices as $service)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('client.services.show', $service) }}" class="text-indigo-600 font-medium">{{ $service->product->name ?? 'Service #' . $service->id }}</a>
                    @if($service->domain) <span class="text-slate-500 ml-2 text-xs">{{ $service->domain }}</span> @endif
                </td>
                <td class="px-4 py-3 text-slate-500">${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</td>
                <td class="px-4 py-3 text-slate-500">Due {{ $service->next_due_date?->format('d M Y') ?? 'N/A' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Active</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
