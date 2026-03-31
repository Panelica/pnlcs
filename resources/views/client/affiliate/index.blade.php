@extends('client.layouts.app')
@section('title', 'Affiliate Program')
@section('styles')
<style>
    .affiliate-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
    @media (max-width: 768px) { .affiliate-stats { grid-template-columns: repeat(2, 1fr); } }
    .aff-stat { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; text-align: center; }
    .aff-stat-value { font-size: 24px; font-weight: 700; color: #1a4d80; }
    .aff-stat-label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
    .referral-link-box { display: flex; gap: 8px; }
    .referral-link-box input { flex: 1; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Affiliate Program</h1>
</div>

<div class="affiliate-stats">
    <div class="aff-stat">
        <div class="aff-stat-value">{{ $stats['referrals'] ?? 0 }}</div>
        <div class="aff-stat-label">Total Referrals</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-value">{{ $stats['signups'] ?? 0 }}</div>
        <div class="aff-stat-label">Signups</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-value" style="color:#3c763d;">${{ number_format($stats['earnings'] ?? 0, 2) }}</div>
        <div class="aff-stat-label">Total Earnings</div>
    </div>
    <div class="aff-stat">
        <div class="aff-stat-value" style="color:#8a6d3b;">${{ number_format($stats['pending'] ?? 0, 2) }}</div>
        <div class="aff-stat-label">Pending</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Your Referral Link</div>
    <div class="card-body">
        <p style="font-size:13px; color:#666; margin-bottom:12px;">Share this link to earn commissions on referred signups.</p>
        <div class="referral-link-box">
            <input type="text" id="refLink" class="form-control" value="{{ $referralLink ?? url(/) . ?ref= . (auth()->user()->id ?? ) }}" readonly>
            <button type="button" class="btn btn-default" onclick="document.getElementById('refLink').select(); document.execCommand('copy'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 2000)">Copy</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Commission History</div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions ?? [] as $comm)
                <tr>
                    <td style="color:#777;">{{ $comm->created_at?->format('d M Y') }}</td>
                    <td>{{ $comm->referredClient->email ?? '-' }}</td>
                    <td style="text-transform:capitalize;">{{ $comm->type ?? 'signup' }}</td>
                    <td style="font-weight:500; color:#3c763d;">${{ number_format($comm->amount, 2) }}</td>
                    <td><span class="badge badge-{{ strtolower($comm->status ?? 'pending') }}">{{ ucfirst($comm->status ?? 'pending') }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:32px; color:#999;">No commissions yet. Start referring clients!</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
