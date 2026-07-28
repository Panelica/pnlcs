@extends("admin.layouts.app")
@section("title", __("admin.domains.title"))
@section("content")

<div class="page-header">
    <h1>{{ __('admin.domains.title') }}</h1>
    <span style="font-size:13px;color:#666;">{{ $domains->total() }} total</span>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 16px;">
        <form method="GET" action="{{ route("admin.domains.index") }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                <label class="form-label">{{ __('common.actions.search') }}</label>
                <input type="text" name="search" value="{{ request("search") }}"
                    placeholder="{{ __('admin.domains.search_placeholder') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('admin.domains.status_label') }}</label>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">{{ __('common.misc.all_statuses') }}</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request("status") == $s ? "selected" : "" }}>{{ ucwords(str_replace("_", " ", $s)) }}</option>
                    @endforeach
                </select>
            </div>
            @if($registrars->count() > 0)
            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('admin.domains.registrar_label') }}</label>
                <select name="registrar" class="form-control" style="width:auto;">
                    <option value="">{{ __('admin.domains.all_registrars') }}</option>
                    @foreach($registrars as $r)
                    <option value="{{ $r }}" {{ request("registrar") == $r ? "selected" : "" }}>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('admin.domains.sort') }}</label>
                <select name="sort" class="form-control" style="width:auto;">
                    <option value="created_at" {{ request("sort","created_at") == "created_at" ? "selected" : "" }}>{{ __('admin.domains.sort_created') }}</option>
                    <option value="expiry_date" {{ request("sort") == "expiry_date" ? "selected" : "" }}>{{ __('admin.domains.sort_expiry') }}</option>
                    <option value="registration_date" {{ request("sort") == "registration_date" ? "selected" : "" }}>{{ __('admin.domains.sort_registration') }}</option>
                    <option value="domain" {{ request("sort") == "domain" ? "selected" : "" }}>{{ __('admin.domains.sort_domain') }}</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-default">{{ __('common.actions.filter') }}</button>
                @if(request()->hasAny(["search","status","registrar","sort"]))
                <a href="{{ route("admin.domains.index") }}" class="btn btn-default" style="margin-left:4px;">{{ __('common.actions.reset') }}</a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('common.table.domain') }}</th>
                <th>{{ __('common.table.client') }}</th>
                <th>{{ __('common.table.registrar') }}</th>
                <th>{{ __('admin.domains.registered') }}</th>
                <th>{{ __('admin.domains.expires') }}</th>
                <th>{{ __('common.table.status') }}</th>
                <th>{{ __('common.table.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($domains as $domain)
            @php
            $expirySoon = $domain->expiry_date && $domain->expiry_date->diffInDays(now()) <= 30 && $domain->expiry_date->isFuture();
            $expired = $domain->expiry_date && $domain->expiry_date->isPast();
            $badgeClass = match(strtolower($domain->status ?? "")) {
                "active"  => "badge-active",
                "pending" => "badge-pending",
                "expired" => "badge-terminated",
                default   => "badge-cancelled",
            };
            @endphp
            <tr>
                <td style="font-family:monospace;font-size:13px;font-weight:500;">{{ $domain->domain }}</td>
                <td>
                    @if($domain->client)
                    <a href="{{ route("admin.clients.show", $domain->client_id) }}" style="color:#337ab7;text-decoration:none;">{{ $domain->client->full_name }}</a>
                    @else N/A @endif
                </td>
                <td style="color:#666;">{{ ucfirst($domain->registrar ?? "-") }}</td>
                <td style="color:#666;">{{ $domain->registration_date?->format(date_fmt()) ?? "-" }}</td>
                <td style="color:{{ $expired ? "#c43c35" : ($expirySoon ? "#d68100" : "#666") }};font-weight:{{ ($expired || $expirySoon) ? "600" : "400" }};">
                    {{ $domain->expiry_date?->format(date_fmt()) ?? "-" }}
                    @if($expirySoon) <small style="font-size:11px;">(soon)</small> @endif
                    @if($expired) <small style="font-size:11px;">(expired)</small> @endif
                </td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($domain->status ?? "") }}</span></td>
                <td>
                    <a href="{{ route("admin.domains.show", $domain) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#999;">{{ __('admin.domains.no_domains') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $domains->withQueryString()->links() }}
    </div>
</div>

@endsection
