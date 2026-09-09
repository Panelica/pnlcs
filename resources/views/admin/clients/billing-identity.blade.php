@extends("admin.layouts.app")
@section("title", __("admin.clients.billing_identity"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.clients.billing_identity') }}</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="{{ route('admin.clients.billing.csv', request()->only('search', 'only_missing')) }}" class="btn btn-default btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>{{ __('common.actions.export_csv') }}</a>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-default btn-sm">{{ __('admin.clients.title') }}</a>
    </div>
</div>

{{-- The count first: while invoices are issued by hand, "whose details are
     missing" is the question this line answers at the start of the month. --}}
@if($missingCount > 0)
<div style="padding:10px 14px;background:#fcf8e3;border:1px solid #faebcc;border-radius:4px;color:#8a6d3b;font-size:13px;margin-bottom:14px;">
    {{ __('admin.clients.billing_missing_count', ['count' => $missingCount]) }}
</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 16px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                <label class="form-label">{{ __('common.actions.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="only_missing" value="1" {{ request()->boolean('only_missing') ? 'checked' : '' }}>
                    {{ __('admin.clients.billing_only_missing') }}
                </label>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="table" style="width:100%;font-size:13px;">
            <thead>
                <tr>
                    <th>{{ __('admin.clients.name') }}</th>
                    <th>{{ __('admin.clients.billing_type') }}</th>
                    <th>{{ __('client.form.company_title') }}</th>
                    <th>{{ __('admin.clients.billing_tax_office') }}</th>
                    <th>{{ __('common.form.tax_id') }}</th>
                    <th>{{ __('admin.clients.billing_national_id') }}</th>
                    <th>{{ __('common.form.address') }}</th>
                    <th>{{ __('common.form.phone') }}</th>
                    <th>{{ __('admin.clients.billing_missing_column') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                @php $gaps = $missing[$client->id] ?? []; @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.clients.show', $client) }}" style="color:#337ab7;font-weight:600;">{{ $client->display_name }}</a>
                        <div style="color:#999;font-size:11px;">{{ $client->email }}</div>
                    </td>
                    <td>{{ $client->client_type ? __('admin.clients.billing_type_'.($client->client_type === 'company' ? 'company' : 'individual')) : '—' }}</td>
                    <td>{{ $client->client_type === 'company' ? ($client->company_name ?: '—') : '—' }}</td>
                    <td>{{ $client->client_type === 'company' ? ($client->tax_office ?: '—') : '—' }}</td>
                    <td style="font-family:monospace;">{{ $client->client_type === 'company' ? ($client->tax_id ?: '—') : '—' }}</td>
                    <td style="font-family:monospace;">{{ $client->client_type === 'individual' ? ($client->national_id ?: '—') : '—' }}</td>
                    <td>{{ $client->address1 ?: '—' }}@if($client->city)<div style="color:#999;font-size:11px;">{{ $client->city }} / {{ $client->country }}</div>@endif</td>
                    <td>{{ $client->full_phone ?: '—' }}</td>
                    <td>
                        @if($gaps)
                            <span style="color:#a94442;font-size:12px;">{{ implode(', ', \App\Support\BillingIdentity::labels($gaps)) }}</span>
                        @else
                            <span style="color:#3c763d;font-size:12px;">✓</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding:20px;text-align:center;color:#999;">{{ __('common.empty.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
