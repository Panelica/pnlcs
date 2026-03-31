@extends('admin.layouts.app')
@section('title', 'Affiliates')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Affiliate Program</h1>
</div>
<div class="card">
    @if(($affiliates ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No affiliates registered.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Client</th><th>Affiliate Code</th><th>Visits</th><th>Signups</th><th>Earnings</th><th>Balance</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($affiliates as $aff)
        <tr>
            <td><a href="{{ route('admin.clients.show', $aff->client) }}" style="color:#337ab7;font-weight:600;">{{ $aff->client->full_name ?? 'N/A' }}</a></td>
            <td style="font-family:monospace;">{{ $aff->code }}</td>
            <td>{{ $aff->visits ?? 0 }}</td>
            <td>{{ $aff->signups ?? 0 }}</td>
            <td>${{ number_format($aff->earnings ?? 0, 2) }}</td>
            <td style="font-weight:600;color:#5cb85c;">${{ number_format($aff->balance ?? 0, 2) }}</td>
            <td><span class="badge-{{ $aff->active ? 'active' : 'suspended' }}">{{ $aff->active ? 'Active' : 'Inactive' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.affiliates.withdraw', $aff) }}" style="display:inline;" onsubmit="return confirm('Process withdrawal?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-xs">Withdraw</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($affiliates, 'links'))
    <div style="padding:10px 15px;">{{ $affiliates->links() }}</div>
    @endif
    @endif
</div>
@endsection
