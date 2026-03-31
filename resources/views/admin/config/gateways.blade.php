@extends('admin.layouts.app')
@section('title', 'Payment Gateways')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Payment Gateways</h1>
    <button type="button" onclick="document.getElementById('modal-add-gw').style.display='flex'" class="btn btn-primary btn-sm">+ Add Gateway</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($gateways ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No payment gateways configured.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Gateway Name</th><th>Display Name</th><th>Type</th><th>Order</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($gateways as $gw)
        <tr>
            <td style="font-family:monospace;">{{ $gw->gateway_name }}</td>
            <td style="font-weight:600;">{{ $gw->description }}</td>
            <td style="text-transform:capitalize;">{{ $gw->type ?? 'Standard' }}</td>
            <td>{{ $gw->order_num ?? 0 }}</td>
            <td><span class="badge-{{ $gw->disabled ? 'suspended' : 'active' }}">{{ $gw->disabled ? 'Disabled' : 'Active' }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.config.gateways.edit', $gw) }}" class="btn btn-default btn-xs">Configure</a>
                <form method="POST" action="{{ route('admin.config.gateways.destroy', $gw) }}" style="display:inline;" onsubmit="return confirm('Remove gateway {{ $gw->gateway_name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-gw" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-gw').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Payment Gateway</h4>
            <button type="button" onclick="document.getElementById('modal-add-gw').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.gateways.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Gateway Module Name</label><input type="text" name="gateway_name" required class="form-control" placeholder="stripe, paypal, banktransfer"></div>
                <div class="form-group"><label class="form-label">Display Name</label><input type="text" name="description" required class="form-control" placeholder="Credit Card (Stripe)"></div>
                <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="order_num" value="0" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-gw').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Gateway</button>
            </div>
        </form>
    </div>
</div>
@endsection
