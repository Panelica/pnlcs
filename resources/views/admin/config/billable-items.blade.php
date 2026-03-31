@extends('admin.layouts.app')
@section('title', 'Billable Items')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Billable Items</h1>
    <button type="button" onclick="document.getElementById('modal-add-bi').style.display='flex'" class="btn btn-primary btn-sm">+ Add Item</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($billableItems ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No billable items configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Description</th><th>Amount</th><th>Type</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($billableItems as $item)
        <tr>
            <td style="font-weight:600;">{{ $item->description }}</td>
            <td>${{ number_format($item->amount, 2) }}</td>
            <td style="text-transform:capitalize;">{{ $item->type ?? 'standard' }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.billable-items.destroy', $item) }}" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-bi" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-bi').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Billable Item</h4>
            <button type="button" onclick="document.getElementById('modal-add-bi').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.billable-items.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Amount ($)</label><input type="number" name="amount" step="0.01" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="standard">Standard</option><option value="credit">Credit</option><option value="debit">Debit</option></select></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-bi').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Item</button>
            </div>
        </form>
    </div>
</div>
@endsection
