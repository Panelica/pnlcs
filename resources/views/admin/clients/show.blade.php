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
            <span style="color:#3c763d;font-weight:600;">{{ money_fmt($client->credit) }}</span>
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
    <div class="stat-card" style="border-color:#d9534f;"><div class="stat-value" style="color:#d9534f;">{{ money_fmt($unpaidInvoices) }}</div><div class="stat-label">{{ __('admin.clients.unpaid') }}</div></div>
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
                    <tr><td style="padding:5px 0;color:#777;">{{ __('common.form.tax_id') }}</td><td style="padding:5px 0;">{{ $client->tax_id ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.email') }}</td><td style="padding:5px 0;"><a href="mailto:{{ $client->email }}" style="color:#337ab7;">{{ $client->email }}</a></td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('common.form.billing_email') }}</td><td style="padding:5px 0;">@if($client->billing_email)<a href="mailto:{{ $client->billing_email }}" style="color:#337ab7;">{{ $client->billing_email }}</a>@else - @endif</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.phone') }}</td><td style="padding:5px 0;">{{ $client->full_phone ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.address') }}</td><td style="padding:5px 0;">{{ $client->address1 ?: '-' }}@if($client->city)<br>{{ $client->city }}{{ $client->state ? ', '.$client->state : '' }} {{ $client->postcode }}@endif</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.country') }}</td><td style="padding:5px 0;">{{ $client->country ?: '-' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.registered') }}</td><td style="padding:5px 0;">{{ $client->created_at->format(date_fmt()) }}</td></tr>
                </table>
            </div>
        </div>
        @if($customFields->isNotEmpty())
        <div class="panel" style="margin-top:10px;">
            <div class="panel-heading panel-primary">{{ __('admin.clients.custom_fields') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    @foreach($customFields as $field)
                    <tr><td style="padding:5px 0;color:#777;width:40%;">{{ $field->field_name }}</td><td style="padding:5px 0;font-weight:600;">{{ $field->values->first()?->value ?: '-' }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif
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
                    <tr style="border-top:1px solid #eee;"><td style="padding:8px 0 5px;color:#777;">{{ __('admin.clients.credit_balance') }}</td><td style="padding:8px 0 5px;font-weight:600;color:#5cb85c;">{{ money_fmt($client->credit) }}</td></tr>
                </table>
            </div>
        </div>
        <div class="panel" style="margin-top:10px;">
            <div class="panel-heading panel-primary">{{ __('admin.clients.other_info') }}</div>
            <div class="panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#777;width:50%;">{{ __('admin.clients.status') }}</td><td style="padding:5px 0;"><span class="badge-{{ strtolower($client->status->value) }}">{{ ucfirst($client->status->value) }}</span></td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.tax_exempt') }}</td><td style="padding:5px 0;">{{ $client->tax_exempt ? 'Yes' : 'No' }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.created') }}</td><td style="padding:5px 0;">{{ $client->created_at->format(date_fmt()) }}</td></tr>
                    <tr><td style="padding:5px 0;color:#777;">{{ __('admin.clients.last_login') }}</td><td style="padding:5px 0;">{{ $client->users->max('last_login')?->diffForHumans() ?? __('admin.clients.never') }}</td></tr>
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
                    <span style="color:#999;">{{ $note->created_at->timezone(display_tz())->format(datetime_fmt()) }}{{ $note->sticky ? ' — ' . __('admin.clients.pinned') : '' }}</span>
                </div>
                @empty
                <p style="font-size:12px;color:#999;margin-top:8px;">{{ __('admin.clients.no_notes') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@elseif($tab === 'services')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <div style="font-weight:600;">{{ __('admin.clients.tab_services') }}</div>
    <button type="button" class="btn btn-primary btn-sm" onclick="var f=document.getElementById('add-service-form');f.style.display=f.style.display==='none'?'block':'none';">{{ __('admin.clients.add_service') }}</button>
</div>

<div class="card" id="add-service-form" style="display:none;margin-bottom:16px;">
    <div class="card-header"><strong>{{ __('admin.clients.add_service') }}</strong></div>
    <div class="card-body">
        <p class="text-muted" style="font-size:12px;margin-bottom:14px;">{{ __('admin.clients.add_service_hint') }}</p>
        @if($errors->any())
        <div class="alert alert-danger" style="font-size:13px;">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.clients.services.store', $client) }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.product') }} <span style="color:#d9534f;">*</span></label>
                    <select name="product_id" class="form-control" required>
                        <option value="">—</option>
                        @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.clients.service_server') }}</label>
                    <select name="server_id" id="svc-server" class="form-control">
                        <option value="">{{ __('admin.clients.service_no_server') }}</option>
                        @foreach($servers as $s)
                        <option value="{{ $s->id }}" @selected(old('server_id')==$s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.domain') }}</label>
                    <input type="text" name="domain" value="{{ old('domain') }}" class="form-control" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.billing_cycle') }} <span style="color:#d9534f;">*</span></label>
                    <select name="billing_cycle" class="form-control" required>
                        @foreach(['Monthly','Quarterly','Semi-Annually','Annually','Biennially','Triennially','One-Time'] as $c)
                        <option value="{{ $c }}" @selected(old('billing_cycle')==$c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.amount') }} <span style="color:#d9534f;">*</span></label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', '0.00') }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('admin.clients.next_due') }}</label>
                    <input type="date" name="next_due_date" value="{{ old('next_due_date') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.table.status') }} <span style="color:#d9534f;">*</span></label>
                    <select name="status" class="form-control" required>
                        <option value="active" @selected(old('status','active')=='active')>Active</option>
                        <option value="pending" @selected(old('status')=='pending')>Pending</option>
                        <option value="suspended" @selected(old('status')=='suspended')>Suspended</option>
                        <option value="cancelled" @selected(old('status')=='cancelled')>Cancelled</option>
                        <option value="terminated" @selected(old('status')=='terminated')>Terminated</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="link-account-row" style="margin-top:12px;display:none;">
                <label class="form-label">{{ __('admin.clients.link_existing') }}</label>
                <select name="link_user_id" id="link-user-id" class="form-control">
                    <option value="">{{ __('admin.clients.link_none') }}</option>
                </select>
                <div class="text-muted" style="font-size:12px;margin-top:4px;">{{ __('admin.clients.link_existing_hint') }}</div>
            </div>
            <label style="display:flex;align-items:flex-start;gap:8px;margin-top:12px;font-size:13px;cursor:pointer;">
                <input type="checkbox" name="provision" value="1" id="svc-provision" {{ old('provision') ? 'checked' : '' }} style="margin-top:3px;">
                <span><strong>{{ __('admin.clients.provision_now') }}</strong><br><span class="text-muted" style="font-size:12px;">{{ __('admin.clients.provision_now_hint') }}</span></span>
            </label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px;">{{ __('admin.clients.add_service') }}</button>
        </form>
        <script>
        (function () {
            var srv = document.getElementById('svc-server'),
                link = document.getElementById('link-user-id'),
                row = document.getElementById('link-account-row'),
                prov = document.getElementById('svc-provision');
            if (!srv || !link || !row) return;
            var base = "{{ url('admin/servers') }}";
            function toggleRow() { row.style.display = (prov && prov.checked) ? 'none' : (srv.value ? 'block' : 'none'); }
            function loadAccounts() {
                link.innerHTML = '<option value="">{{ __('admin.clients.link_none') }}</option>';
                if (!srv.value) { toggleRow(); return; }
                fetch(base + '/' + srv.value + '/accounts', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        (d.accounts || []).forEach(function (a) {
                            var o = document.createElement('option');
                            o.value = a.id;
                            o.textContent = a.username + (a.email ? ' (' + a.email + ')' : '') + (a.status && a.status !== 'active' ? ' — ' + a.status : '');
                            link.appendChild(o);
                        });
                    })
                    .catch(function () {});
                toggleRow();
            }
            srv.addEventListener('change', loadAccounts);
            if (prov) prov.addEventListener('change', toggleRow);
            if (srv.value) loadAccounts();
        })();
        </script>
    </div>
</div>

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
            <td>{{ money_fmt($service->amount) }}</td>
            <td>{{ $service->next_due_date?->format(date_fmt()) ?? '-' }}</td>
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
            <th>{{ __('common.table.domain') }}</th><th>{{ __('common.table.registrar') }}</th><th>{{ __('admin.clients.registered') }}</th><th>{{ __('admin.domains.expiry_date') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($domains as $domain)
        <tr>
            <td style="font-weight:600;"><a href="{{ route('admin.domains.show', $domain) }}" style="text-decoration:none;color:inherit;">{{ $domain->domain }}</a></td>
            <td>{{ $domain->registrar ?? '-' }}</td>
            <td>{{ $domain->registration_date?->format(date_fmt()) ?? '-' }}</td>
            <td>{{ $domain->expiry_date?->format(date_fmt()) ?? '-' }}</td>
            <td><span class="badge-{{ strtolower($domain->status) }}">{{ ucfirst($domain->status) }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.domains.show', $domain) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
            </td>
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
            <td>{{ $inv->date?->format(date_fmt()) ?? '-' }}</td>
            <td>{{ $inv->due_date?->format(date_fmt()) ?? '-' }}</td>
            <td style="font-weight:600;">{{ money_fmt($inv->total) }}</td>
            <td><span class="badge-{{ strtolower($inv->status) }}">{{ invoice_status_label($inv->status) }}</span></td>
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
        <span style="font-size:11px;color:#999;">{{ $note->created_at->timezone(display_tz())->format(datetime_fmt()) }}{{ $note->sticky ? ' — ' . __('admin.clients.pinned') : '' }}</span>
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
            <td style="font-size:12px;white-space:nowrap;">{{ $log->created_at->timezone(display_tz())->format(datetime_fmt()) }}</td>
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
