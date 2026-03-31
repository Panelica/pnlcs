@extends("client.layouts.app")
@section("title", "Affiliate Program")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Affiliate Program</h1>
        <p class="pn-page-subtitle">Earn commissions by referring new clients.</p>
    </div>
</div>

<div class="pn-aff-grid mb-24">
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val">{{ $stats["referrals"] ?? 0 }}</div>
        <div class="pn-aff-lbl">Total Referrals</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val">{{ $stats["signups"] ?? 0 }}</div>
        <div class="pn-aff-lbl">Signups</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val" style="color:var(--success)">${{ number_format($stats["earnings"] ?? 0, 2) }}</div>
        <div class="pn-aff-lbl">Total Earnings</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val" style="color:var(--warning)">${{ number_format($stats["pending"] ?? 0, 2) }}</div>
        <div class="pn-aff-lbl">Pending</div>
    </div>
</div>

<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">Your Referral Link</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">Share your unique referral link to earn commissions when referred visitors sign up and make a purchase.</p>
        <div style="display:flex;gap:8px;max-width:520px">
            <input type="text" id="refLink" class="form-control" value="{{ $referralLink ?? url("/") . "?ref=" . (auth()->user()->id ?? "") }}" readonly style="background:#f8fafc;font-size:13px">
            <button type="button" class="btn btn-primary" id="copyBtn" onclick="copyLink()" style="flex-shrink:0">Copy Link</button>
        </div>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">Commission History</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Referred Client</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions ?? [] as $comm)
                <tr>
                    <td class="text-muted text-sm">{{ $comm->created_at?->format("d M Y") }}</td>
                    <td>{{ $comm->referredClient->email ?? "-" }}</td>
                    <td style="text-transform:capitalize">{{ $comm->type ?? "signup" }}</td>
                    <td style="font-weight:700;color:var(--success)">${{ number_format($comm->amount, 2) }}</td>
                    <td><span class="badge badge-{{ strtolower($comm->status ?? "pending") }}">{{ ucfirst($comm->status ?? "pending") }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128200;</div>
                            <p>No commissions yet. Start sharing your referral link!</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@section("scripts")
<script>
function copyLink() {
    const el = document.getElementById("refLink");
    const btn = document.getElementById("copyBtn");
    el.select(); el.setSelectionRange(0, 99999);
    navigator.clipboard?.writeText(el.value).catch(() => document.execCommand("copy"));
    btn.textContent = "Copied!"; btn.style.background = "var(--success)";
    setTimeout(() => { btn.textContent = "Copy Link"; btn.style.background = ""; }, 2500);
}
</script>
@endsection

@endsection
