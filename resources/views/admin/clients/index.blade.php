@extends("admin.layouts.app")
@section("title", "Clients")
@section("content")

<div class="page-header">
    <h1>Clients</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="{{ route("admin.clients.index") }}?export=csv" class="btn btn-default btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <a href="{{ route("admin.clients.create") }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add New Client
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 16px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request("search") }}"
                    placeholder="Name, email, company..."
                    class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request("status") == "active" ? "selected" : "" }}>Active</option>
                    <option value="inactive" {{ request("status") == "inactive" ? "selected" : "" }}>Inactive</option>
                    <option value="closed" {{ request("status") == "closed" ? "selected" : "" }}>Closed</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Group</label>
                <select name="group_id" class="form-control" style="width:auto;">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                    <option value="{{ $group->id }}" {{ request("group_id") == $group->id ? "selected" : "" }}>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-default">Filter</button>
                @if(request()->hasAny(["search","status","group_id"]))
                <a href="{{ route("admin.clients.index") }}" class="btn btn-default" style="margin-left:4px;">Reset</a>
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
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Company</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clients as $client)
            @php
            $statusVal = is_object($client->status) ? $client->status->value : $client->status;
            $badgeClass = match(strtolower($statusVal)) {
                "active"   => "badge-active",
                "inactive" => "badge-cancelled",
                "closed"   => "badge-terminated",
                default    => "badge-cancelled",
            };
            @endphp
            <tr>
                <td style="color:#666;font-family:monospace;font-size:12px;">{{ $client->id }}</td>
                <td><a href="{{ route("admin.clients.show", $client) }}" style="color:#337ab7;text-decoration:none;font-weight:500;">{{ $client->full_name }}</a></td>
                <td>{{ $client->email }}</td>
                <td>{{ $client->company_name ?? "-" }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($statusVal) }}</span></td>
                <td style="color:#666;">{{ $client->created_at->format("d M Y") }}</td>
                <td>
                    <a href="{{ route("admin.clients.show", $client) }}" class="btn btn-default btn-xs">View</a>
                    <a href="{{ route("admin.clients.edit", $client) }}" class="btn btn-default btn-xs">Edit</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#999;">No clients found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;">
        {{ $clients->withQueryString()->links() }}
    </div>
</div>

@endsection
