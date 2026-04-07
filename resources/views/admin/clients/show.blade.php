@extends('admin.layouts.app')
@section('title', '#' . $client->id . ' - ' . $client->full_name)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>#{{ $client->id }} - {{ $client->full_name }}</h1>
    <div style="display:flex;gap:6px;align-items:center;">
                <form method="POST" action="{{ route('admin.clients.impersonate', $client) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ __('admin.clients.confirm_login_as') }}')">
                {{ __('admin.clients.login_as_client') }}
            </button>
        </form>
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-primary btn-sm">{{ __('admin.clients.edit_client_btn') }}</a>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-default btn-sm">{{ __('common.actions.close') }}</a>
        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.clients.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">{{ __('common.actions.delete') }}</button>
        </form>
    </div>
</div>

{{-- Info Bar --}}
<div class="card" style="margin-bottom:15px;">
    <div class="card-body" style="padding:10px 15px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:8px;">
            <strong style="font-size:13px;">{{ __('admin.clients.status') }}:</strong>
            <span class="badge-{{ strtolower($client->status->value) }}">{{ ucfirst($client->status->value) }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <strong style="font-size:13px;">{{ __('admin.clients.credit_balance') }}:</strong>
            <span style="color:#3c763d;font-weight:600;">${{ number_format($client->credit, 2) }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <strong style="font-size:13px;">{{ __('admin.clients.tax_exempt') }}:</strong>
            <span style="font-size:13px;">{{ $client->tax_exempt ? 'Yes' : 'No' }}</span>
        </div>
    </div>
</div>

{{-- Stats Row --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:15px;">
    <div class="stat-card"><div class="stat-value">{{ $serviceCount }}</div><div class="stat-label">{{ __('admin.clients.services') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ $domainCount }}</div><div class="stat-label">{{ __('admin.clients.domains') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ $invoiceCount }}</div><div class="stat-label">{{ __('admin.clients.invoices') }}</div></div>
    <div class="stat-card"><div class="stat-value">{{ $ticketCount }}</div><div class="stat-label">{{ __('admin.clients.tickets') }}</div></div>
    <div class="stat-card" style="border-color:#d9534f;"><div class="stat-value" style="color:#d9534f;">${{ number_format($unpaidInvoices, 2) }}</div><div class="stat-label">{{ __('admin.clients.unpaid') }}</div></div>
</div>

{{-- Tab Navigation --}}
@php
$tabs = ['summary'=>__('admin.clients.tab_summary'),'services'=>__('admin.clients.tab_services'),'domains'=>__('admin.clients.tab_domains'),'invoices'=>__('admin.clients.tab_invoices'),'tickets'=>__('admin.clients.tab_tickets'),'notes'=>__('admin.clients.tab_notes'),'log'=>__('admin.clients.tab_log')];
@endphp
<div style="border-bottom:2px solid #ddd;margin-bottom:15px;display:flex;gap:0;">
    @foreach($tabs as $key => $label)
    <a href="{{ route('admin.clients.show', ['client' => $client, 'tab' => $key]) }}"
       style="padding:8px 16px;font-size:13px;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;
              {{ $tab === $key ? 'border-bottom-color:#337ab7;color:#337ab7;font-weight:600;' : 'color:#555;' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Tab Content --}}
@if($tab === 'summary')
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">

    {{-- Column 1 --}}
    <div>
        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.clients.client_information') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#777;width:40%;">{{ __('admin.clients.name') }}</td><td style="padding:5px 0;font-weight:600;">{{ $client->full_name }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.company') }}</td><td style="padding:5px 0;">{{ $client->company_name ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.email') }}</td><td style="padding:5px 0;"><a href="mailto:{{ $client->email }}" style="color:#337ab7;">{{ $client->email }}</a></td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.phone') }}</td><td style="padding:5px 0;">{{ $client->phone_number ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.address') }}</td><td style="padding:5px 0;">{{ $client->address1 ?: '-' }}@if($client->city)<br>{{ $client->city }}{{ $client->state ? ', '.$client->state : '' }} {{ $client->postcode }}@endif</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.country') }}</td><td style="padding:5px 0;">{{ $client->country ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.registered') }}</td><td style="padding:5px 0;">{{ $client->created_at->format('d M Y') }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Column 2 --}}
    <div>
        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.clients.billing_summary') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    @php
                        $paid   = isset($invoiceSummary) ? ($invoiceSummary['paid'] ?? 0) : 0;
                        $unpaid = isset($invoiceSummary) ? ($invoiceSummary['unpaid'] ?? 0) : 0;
                        $ovrd   = isset($invoiceSummary) ? ($invoiceSummary['overdue'] ?? 0) : 0;
                        $total  = isset($invoiceSummary) ? ($invoiceSummary['total'] ?? 0) : 0;
                    @endphp
                    <tr><td style="padding:5px 0;color:#777;width:50%;">{{ __('admin.clients.paid_invoices') }}</td><td style="padding:5px 0;font-weight:600;color:#5cb85c;">{{ $paid }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.unpaid_invoices') }}</td><td style="padding:5px 0;font-weight:600;color:#f0ad4e;">{{ $unpaid }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.overdue_invoices') }}</td><td style="padding:5px 0;font-weight:600;color:#d9534f;">{{ $ovrd }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.total_invoices') }}</td><td style="padding:5px 0;font-weight:600;">{{ $invoiceCount }}</td></tr>
                    <tr style="border-top:1px solid #eee;"><td style="padding:8px 0 5px;color:#777;">{{ __('admin.clients.credit_balance') }}</td><td style="padding:8px 0 5px;font-weight:600;color:#5cb85c;">${{ number_format($client->credit, 2) }}</td></tr>
                </table>
            </div>
        </div>
        <div class="panel" style="margin-top:10px;">
            <div class="panel-heading panel-primary">{{ __('admin.clients.other_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#777;width:50%;">{{ __('admin.clients.status') }}</td><td style="padding:5px 0;"><span class="badge-{{ strtolower($client->status->value) }}">{{ ucfirst($client->status->value) }}</span></td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.tax_exempt') }}</td><td style="padding:5px 0;">{{ $client->tax_exempt ? 'Yes' : 'No' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.created') }}</td><td style="padding:5px 0;">{{ $client->created_at->format('d M Y') }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.last_login') }}</td><td style="padding:5px 0;">{{ $client->last_login?->diffForHumans() ?? __('admin.clients.never') }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Column 3 --}}
    <div>
        <div class="panel">
            <div class="panel-heading panel-primary">{{ __('admin.clients.quick_actions') }}</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:6px;">
                <a href="{{ route('admin.clients.show', ['client' => $client, 'tab' => 'notes']) }}" class="btn btn-default btn-sm" style="width:100%;text-align:left;">{{ __('admin.clients.add_note_link') }}</a>
                <a href="{{ route('admin.tickets.index') }}" class="btn btn-default btn-sm" style="width:100%;text-align:left;">{{ __('admin.clients.new_ticket_link') }}</a>
                <a href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}" class="btn btn-default btn-sm" style="width:100%;text-align:left;">{{ __('admin.clients.new_invoice_link') }}</a>
            </div>
        </div>
        <div class="panel" style="margin-top:10px;">
            <div class="panel-heading panel-primary">{{ __('admin.clients.admin_notes') }}</div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.clients.notes.store', $client) }}">
                    @csrf
                    <div class="form-group">
                        <textarea name="note" rows="4" required placeholder="{{ __('admin.clients.type_note') }}" class="form-control" style="font-size:13px;resize:vertical;"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
                        <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                            <input type="checkbox" name="sticky" value="1"> Sticky
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.clients.add_note') }}</button>
                    </div>
                </form>
                @forelse(($notes ?? collect())->take(3) as $note)
                <div style="margin-top:10px;padding:8px;background:{{ $note->sticky ? '#fffbe6' : '#f9f9f9' }};border:1px solid {{ $note->sticky ? '#e6d200' : '#eee' }};border-radius:3px;font-size:12px;">
                    <p style="margin:0 0 4px;color:#333;">{{ $note->note }}</p>
                    <span style="color:#999;">{{ $note->created_at->format('d M Y H:i') }}{{ $note->sticky ? ' — ' . __('admin.clients.pinned') : '' }}</span>
                </div>
                @empty
                <p style="font-size:12px;color:#999;margin-top:8px;">{{ __('admin.clients.no_notes') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@elseif($tab === 'services')
<div class="card">
    @if($services->isEmpty())
    <div class="card-body" style="text-align:center;color:#999;padding:40px;">{{ __('admin.services.no_services') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.product') }}</th><th>{{ __('common.table.domain') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('admin.clients.next_due') }}</th><th>{{ __('common.table.status') }}</th>
        </tr></thead>
        <tbody>
        @foreach($services as $service)
        <tr>
            <td><a href="{{ route('admin.services.show', $service) }}" style="color:#337ab7;">{{ $service->product?->name ?? 'N/A' }}</a></td>
            <td>{{ $service->domain ?? '-' }}</td>
            <td>{{ $service->billing_cycle }}</td>
            <td>${{ number_format($service->amount, 2) }}</td>
            <td>{{ $service->next_due_date?->format('d M Y') ?? '-' }}</td>
            <td><span class="badge-{{ strtolower($service->status) }}">{{ ucfirst($service->status) }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $services->appends(['tab' => 'services'])->links() }}</div>
    @endif
</div>

@elseif($tab === 'domains')
<div class="card">
    @if($domains->isEmpty())
    <div class="card-body" style="text-align:center;color:#999;padding:40px;">{{ __('admin.domains.no_domains') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.domain') }}</th><th>{{ __('common.table.registrar') }}</th><th>{{ __('admin.clients.registered') }}</th><th>{{ __('admin.domains.expiry_date') }}</th><th>{{ __('common.table.status') }}</th>
        </tr></thead>
        <tbody>
        @foreach($domains as $domain)
        <tr>
            <td style="font-weight:600;">{{ $domain->domain }}</td>
            <td>{{ $domain->registrar ?? '-' }}</td>
            <td>{{ $domain->registration_date?->format('d M Y') ?? '-' }}</td>
            <td>{{ $domain->expiry_date?->format('d M Y') ?? '-' }}</td>
            <td><span class="badge-{{ strtolower($domain->status) }}">{{ ucfirst($domain->status) }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $domains->appends(['tab' => 'domains'])->links() }}</div>
    @endif
</div>

@elseif($tab === 'invoices')
<div class="card">
    @if($invoices->isEmpty())
    <div class="card-body" style="text-align:center;color:#999;padding:40px;">{{ __('admin.invoices.no_invoices') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.invoice_num') }}</th><th>{{ __('common.table.date') }}</th><th>{{ __('common.table.due_date') }}</th><th>{{ __('common.table.total') }}</th><th>{{ __('common.table.status') }}</th>
        </tr></thead>
        <tbody>
        @foreach($invoices as $inv)
        <tr>
            <td><a href="{{ route('admin.invoices.show', $inv) }}" style="color:#337ab7;">{{ $inv->invoice_num }}</a></td>
            <td>{{ $inv->date?->format('d M Y') ?? '-' }}</td>
            <td>{{ $inv->due_date?->format('d M Y') ?? '-' }}</td>
            <td style="font-weight:600;">${{ number_format($inv->total, 2) }}</td>
            <td><span class="badge-{{ strtolower($inv->status) }}">{{ ucfirst($inv->status) }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $invoices->appends(['tab' => 'invoices'])->links() }}</div>
    @endif
</div>

@elseif($tab === 'tickets')
<div class="card">
    @if($tickets->isEmpty())
    <div class="card-body" style="text-align:center;color:#999;padding:40px;">{{ __('admin.tickets.no_tickets') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.id') }}</th><th>{{ __('common.table.department') }}</th><th>{{ __('common.table.subject') }}</th><th>{{ __('common.table.priority') }}</th><th>{{ __('common.table.last_reply') }}</th><th>{{ __('common.table.status') }}</th>
        </tr></thead>
        <tbody>
        @foreach($tickets as $ticket)
        <tr>
            <td style="font-family:monospace;font-size:12px;">{{ $ticket->tid }}</td>
            <td>{{ $ticket->department->name ?? '-' }}</td>
            <td><a href="{{ route('admin.tickets.show', $ticket) }}" style="color:#337ab7;">{{ $ticket->title }}</a></td>
            <td><span style="font-size:11px;">{{ ucfirst($ticket->priority) }}</span></td>
            <td style="font-size:12px;">{{ $ticket->last_reply?->diffForHumans() ?? '-' }}</td>
            <td><span class="badge-{{ strtolower($ticket->status) }}">{{ ucfirst($ticket->status) }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $tickets->appends(['tab' => 'tickets'])->links() }}</div>
    @endif
</div>

@elseif($tab === 'notes')
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.clients.add_note') }}</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.clients.notes.store', $client) }}">
            @csrf
            <div class="form-group">
                <textarea name="note" rows="4" required placeholder="{{ __('admin.clients.type_your_note') }}" class="form-control"></textarea>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
                <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" name="sticky" value="1"> {{ __('admin.clients.sticky_note') }}
                </label>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.clients.add_note') }}</button>
            </div>
        </form>
    </div>
</div>
@forelse($notes as $note)
<div class="card" style="margin-bottom:8px;{{ $note->sticky ? 'border-left:4px solid #f0ad4e;' : '' }}">
    <div class="card-body" style="padding:10px 15px;">
        <p style="margin:0 0 6px;font-size:13px;color:#333;">{{ $note->note }}</p>
        <span style="font-size:11px;color:#999;">{{ $note->created_at->format('d M Y H:i') }}{{ $note->sticky ? " ' &mdash; Pinned'mdash; {{ __('admin.clients.pinned') }}" : '' }}</span>
    </div>
</div>
@empty
<p style="color:#999;font-size:13px;">{{ __('admin.clients.no_notes') }}</p>
@endforelse

@elseif($tab === 'log')
<div class="card">
    @if($logs->isEmpty())
    <div class="card-body" style="text-align:center;color:#999;padding:40px;">{{ __('admin.clients.no_activity') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.date') }}</th><th>{{ __('admin.clients.admin') }}</th><th>{{ __('admin.clients.action') }}</th><th>{{ __('common.table.description') }}</th>
        </tr></thead>
        <tbody>
        @foreach($logs as $log)
        <tr>
            <td style="font-size:12px;white-space:nowrap;">{{ $log->created_at->format('d M Y H:i') }}</td>
            <td>{{ $log->admin ?? '-' }}</td>
            <td>{{ $log->action ?? '-' }}</td>
            <td>{{ $log->description }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $logs->appends(['tab' => 'log'])->links() }}</div>
    @endif
</div>
@endif

@endsection
