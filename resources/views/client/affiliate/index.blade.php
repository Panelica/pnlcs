@extends("client.layouts.app")
@section("title", __("client.affiliates.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.affiliates.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.affiliates.page_subtitle') }}</p>
    </div>
</div>

<div class="pn-aff-grid mb-24">
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val">{{ $stats["referrals"] ?? 0 }}</div>
        <div class="pn-aff-lbl">{{ __('client.affiliates.total_referrals_stat') }}</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val">{{ $stats["signups"] ?? 0 }}</div>
        <div class="pn-aff-lbl">{{ __('client.affiliates.signups') }}</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val" style="color:var(--success)">${{ number_format($stats["earnings"] ?? 0, 2) }}</div>
        <div class="pn-aff-lbl">{{ __('client.affiliates.total_earnings') }}</div>
    </div>
    <div class="pn-card pn-aff-stat">
        <div class="pn-aff-val" style="color:var(--warning)">${{ number_format($stats["pending"] ?? 0, 2) }}</div>
        <div class="pn-aff-lbl">{{ __('client.affiliates.pending') }}</div>
    </div>
</div>

@if($affiliate)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.affiliates.referral_link') }}</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">{{ __('client.affiliates.referral_share_desc') }}</p>
        <div style="display:flex;gap:8px;max-width:520px">
            <input type="text" id="refLink" class="form-control" value="{{ $referralLink }}" readonly style="background:#f8fafc;font-size:13px">
            <button type="button" class="btn btn-primary" id="copyBtn" onclick="copyLink()" style="flex-shrink:0">{{ __('client.affiliates.copy_link') }}</button>
        </div>
    </div>
</div>

@if(($affiliate->balance ?? 0) > 0)
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.affiliates.withdraw') }}</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">
            {{ __('client.affiliates.withdraw_desc', ['minimum' => money_fmt(\App\Models\Setting::get('AffiliateMinPayout', 25))]) }}
        </p>
        <form method="POST" action="{{ route('client.affiliates.withdraw') }}" style="display:flex;gap:8px;max-width:520px;align-items:flex-start">
            @csrf
            <div style="flex:1">
                <input type="number" name="amount" step="0.01" min="0" max="{{ $affiliate->balance }}"
                       value="{{ old('amount') }}" class="form-control" placeholder="0.00">
                @error('amount')<div style="color:#c00;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary" style="flex-shrink:0">{{ __('client.affiliates.withdraw') }}</button>
        </form>
    </div>
</div>
@endif
@else
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.affiliates.join_title') }}</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">{{ __('client.affiliates.join_desc') }}</p>
        <form method="POST" action="{{ route('client.affiliates.activate') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('client.affiliates.join_button') }}</button>
        </form>
    </div>
</div>
@endif

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.affiliates.commission_history') }}</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.date') }}</th>
                    <th>{{ __('client.affiliates.referred_client') }}</th>
                    <th>{{ __('common.table.type') }}</th>
                    <th>{{ __('common.table.amount') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions ?? [] as $comm)
                <tr>
                    <td class="text-muted text-sm">{{ $comm->created_at?->format(date_fmt()) }}</td>
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
                            <p>{{ __('client.affiliates.no_commissions') }}</p>
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
    btn.textContent = "{{ __('client.affiliates.copied') }}"; btn.style.background = "var(--success)";
    setTimeout(() => { btn.textContent = "{{ __('client.affiliates.copy_link') }}"; btn.style.background = ""; }, 2500);
}
</script>
@endsection

@endsection
