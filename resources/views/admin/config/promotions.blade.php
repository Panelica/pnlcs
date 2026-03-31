@extends('admin.layouts.app')
@section('title', 'Promotions')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Promotions &amp; Promo Codes</h1>
    <button type="button" onclick="document.getElementById('modal-add-promo').style.display='flex'" class="btn btn-primary btn-sm">+ Add Promo Code</button>
</div>
<div class="card">
    @if(($promotions ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No promotions configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Uses</th><th>Expires</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($promotions as $promo)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $promo->code }}</td>
            <td style="text-transform:capitalize;">{{ $promo->type }}</td>
            <td>{{ $promo->type === 'percentage' ? $promo->value . '%' : '$' . number_format($promo->value, 2) }}</td>
            <td>{{ $promo->uses ?? 0 }}{{ $promo->max_uses ? ' / ' . $promo->max_uses : '' }}</td>
            <td style="font-size:12px;">{{ $promo->expiry_date?->format('d M Y') ?? 'Never' }}</td>
            <td><span class="badge-{{ $promo->active ? 'active' : 'suspended' }}">{{ $promo->active ? 'Active' : 'Inactive' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.promotions.destroy', $promo) }}" style="display:inline;" onsubmit="return confirm('Delete promo code {{ $promo->code }}?')">
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

<div id="modal-add-promo" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-promo').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:500px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Promo Code</h4>
            <button type="button" onclick="document.getElementById('modal-add-promo').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.promotions.store') }}">
            @csrf
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Promo Code</label><input type="text" name="code" required class="form-control" placeholder="SUMMER20" style="text-transform:uppercase;"></div>
                    <div class="form-group"><label class="form-label">Discount Type</label><select name="type" class="form-control"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount ($)</option></select></div>
                    <div class="form-group"><label class="form-label">Discount Value</label><input type="number" name="value" step="0.01" required class="form-control" placeholder="10"></div>
                    <div class="form-group"><label class="form-label">Max Uses <small style="color:#999;">(0 = unlimited)</small></label><input type="number" name="max_uses" value="0" min="0" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Expiry Date <small style="color:#999;">(optional)</small></label><input type="date" name="expiry_date" class="form-control"></div>
                    <div class="form-group" style="grid-column:span 2;"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="active" value="1" checked> Active</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-promo').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Promo Code</button>
            </div>
        </form>
    </div>
</div>
@endsection
