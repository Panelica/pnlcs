@extends('admin.layouts.app')
@section('title', 'Affiliates')
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Affiliates</h1>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ $totalAffiliates }}</div><div style="color:#888;font-size:13px;">Total Affiliates</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">${{ number_format($totalEarnings, 2) }}</div><div style="color:#888;font-size:13px;">Total Earnings</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">${{ number_format($totalWithdrawn, 2) }}</div><div style="color:#888;font-size:13px;">Total Withdrawn</div></div></div>
    <div class="card"><div class="card-body"><div style="font-size:24px;font-weight:600;">{{ number_format($totalVisitors) }}</div><div style="color:#888;font-size:13px;">Total Visitors</div></div></div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <strong>All Affiliates</strong>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}" style="width:200px;">
            <button class="btn btn-sm btn-default" type="submit">Search</button>
        </form>
    </div>
    <div class="card-body-flush">
        <table class="table table-striped mb-0">
            <thead><tr><th>Client</th><th>Visitors</th><th>Type</th><th>Rate</th><th>Balance</th><th>Withdrawn</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($affiliates as $aff)
            <tr>
                <td><a href="{{ route('admin.affiliates.show', $aff) }}">{{ $aff->client?->first_name }} {{ $aff->client?->last_name }}</a></td>
                <td>{{ number_format($aff->visitors) }}</td>
                <td>{{ ucfirst($aff->pay_type) }}</td>
                <td>{{ $aff->pay_type === 'percentage' ? $aff->pay_amount . '%' : '$' . number_format($aff->pay_amount, 2) }}</td>
                <td><strong>${{ number_format($aff->balance, 2) }}</strong></td>
                <td>${{ number_format($aff->withdrawn, 2) }}</td>
                <td><a href="{{ route('admin.affiliates.show', $aff) }}" class="btn btn-sm btn-default">View</a></td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#999;">No affiliates found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:12px;">{{ $affiliates->links() }}</div>
@endsection
