@extends("admin.layouts.app")
@section("title", "Client Groups")
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Client Groups</h1>
    <button type="button" onclick="openModal('add-group')" class="btn btn-primary btn-sm">+ Add Group</button>
</div>
<div class="card">
    @if(($groups ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">
        <p style="font-size:16px;margin-bottom:5px;">No client groups configured.</p>
        <p style="font-size:12px;">Client groups allow you to categorize clients and apply different settings, discount levels, or payment terms per group.</p>
    </div>
    @else
    <table class="data-table">
        <thead><tr><th>Group Name</th><th>Color</th><th>Clients</th><th>Discount %</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($groups as $group)
        <tr>
            <td style="font-weight:600;">{{ $group->name }}</td>
            <td><span style="display:inline-block;width:20px;height:20px;border-radius:3px;background:{{ $group->color ?? "#ccc" }};"></span></td>
            <td>{{ $group->clients_count ?? 0 }}</td>
            <td>{{ $group->discount_percent ?? 0 }}%</td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-group-{{ $group->id }}')" class="btn btn-default btn-xs">Edit</button>
                <form method="POST" action="{{ route("admin.config.client-groups.destroy", $group) }}" style="display:inline;" onsubmit="return confirm('Delete this group?')">@csrf @method("DELETE")<button type="submit" class="btn btn-danger btn-xs">Delete</button></form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<x-modal name="add-group" title="Add Client Group" maxWidth="sm">
    <form method="POST" action="{{ route("admin.config.client-groups.store") }}">
        @csrf
        <div class="form-group"><label class="form-label">Group Name</label><input type="text" name="name" required class="form-control" placeholder="e.g. VIP, Reseller, Enterprise"></div>
        <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" value="#405189" class="form-control" style="height:38px;padding:3px;"></div>
        <div class="form-group"><label class="form-label">Discount Percentage</label><input type="number" name="discount_percent" value="0" min="0" max="100" step="0.01" class="form-control"></div>
        <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-group')" class="btn btn-default btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Create Group</button>
        </div>
    </form>
</x-modal>

@endsection
