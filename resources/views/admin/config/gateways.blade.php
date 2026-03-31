@extends("admin.layouts.app")
@section("title", "Payment Gateways")
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Payment Gateways</h1>
    <p style="color:#666;font-size:13px;margin:0;">Configure payment methods accepted from clients.</p>
</div>

@php
// Built-in gateway modules
\ = [
    ["name" => "banktransfer",    "label" => "Bank Transfer",      "icon" => "fas fa-university",     "desc" => "Accept payments via manual bank wire transfer."],
    ["name" => "paypal",          "label" => "PayPal",             "icon" => "fab fa-paypal",          "desc" => "Accept payments via PayPal (standard checkout)."],
    ["name" => "stripe",          "label" => "Stripe",             "icon" => "fab fa-stripe-s",        "desc" => "Accept credit/debit card payments via Stripe."],
    ["name" => "coingate",        "label" => "CoinGate (Crypto)",  "icon" => "fab fa-bitcoin",         "desc" => "Accept cryptocurrency payments via CoinGate."],
    ["name" => "iyzico",          "label" => "iyzico",             "icon" => "fas fa-credit-card",     "desc" => "Accept card payments via iyzico (Turkey)."],
    ["name" => "paytm",           "label" => "Paytm",              "icon" => "fas fa-mobile-alt",      "desc" => "Accept payments via Paytm (India)."],
    ["name" => "mollie",          "label" => "Mollie",             "icon" => "fas fa-credit-card",     "desc" => "Accept card, iDEAL and other EU payments."],
];

// Build a lookup from DB settings
\ = [];
foreach (\ ?? [] as \) {
    \[\->gateway_name] = \;
}
@endphp

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px;"></th>
                <th>Gateway</th>
                <th>Description</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach(\ as \)
            @php
                \ = \[\["name"]] ?? null;
                \ = \ ? !\->disabled : false;
                \ = "gw-modal-" . \["name"];
            @endphp
            <tr>
                <td style="text-align:center;font-size:18px;color:#555;"><i class="{{ \["icon"] }}"></i></td>
                <td style="font-weight:600;">{{ \["label"] }}</td>
                <td style="font-size:13px;color:#666;">{{ \["desc"] }}</td>
                <td>
                    <span class="badge {{ \ ? "badge-active" : "badge-suspended" }}">
                        {{ \ ? "Active" : "Inactive" }}
                    </span>
                </td>
                <td style="text-align:right;">
                    <button type="button" onclick="document.getElementById('{{ \ }}').style.display='flex'" class="btn btn-default btn-xs">Configure</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- Configure Modals --}}
@foreach(\ as \)
    @php
        \ = \[\["name"]] ?? null;
        \ = \ ? (array)(\->settings ?? []) : [];
        \ = \ ? !\->disabled : false;
        \ = "gw-modal-" . \["name"];
    @endphp
    <div id="{{ \ }}" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
        <div style="position:relative;background:#fff;border-radius:4px;width:520px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
            <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
                <h4 style="margin:0;font-size:16px;"><i class="{{ \["icon"] }}" style="margin-right:6px;"></i> {{ \["label"] }}</h4>
                <button type="button" onclick="this.closest('[id]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.config.gateways.settings.update', \["name"]) }}">
                @csrf
                <div style="padding:20px;">
                    <p style="font-size:13px;color:#777;margin-bottom:15px;">{{ \["desc"] }}</p>

                    <div class="form-group">
                        <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="settings[visible]" value="1" {{ \ ? "checked" : "" }}> Enable this gateway (show to clients)
                        </label>
                    </div>

                    @if(\["name"] === "banktransfer")
                        <div class="form-group"><label class="form-label">Bank Name</label><input type="text" name="settings[bank_name]" value="{{ \["bank_name"] ?? "" }}" class="form-control" placeholder="e.g. Ziraat Bankası"></div>
                        <div class="form-group"><label class="form-label">Account Holder</label><input type="text" name="settings[account_name]" value="{{ \["account_name"] ?? "" }}" class="form-control"></div>
                        <div class="form-group"><label class="form-label">IBAN</label><input type="text" name="settings[iban]" value="{{ \["iban"] ?? "" }}" class="form-control" placeholder="e.g. TR00 0000 0000 0000 0000 0000 00"></div>
                        <div class="form-group"><label class="form-label">Payment Instructions</label><textarea name="settings[instructions]" rows="3" class="form-control" placeholder="Instructions shown to client after order">{{ \["instructions"] ?? "" }}</textarea></div>
                    @elseif(\["name"] === "paypal")
                        <div class="form-group"><label class="form-label">PayPal Email</label><input type="email" name="settings[email]" value="{{ \["email"] ?? "" }}" class="form-control" placeholder="paypal@yourdomain.com"></div>
                        <div class="form-group"><label class="form-label">Test Mode</label>
                            <select name="settings[testmode]" class="form-control">
                                <option value="0" {{ (\["testmode"] ?? "0") == "0" ? "selected" : "" }}>Live</option>
                                <option value="1" {{ (\["testmode"] ?? "0") == "1" ? "selected" : "" }}>Sandbox / Test</option>
                            </select>
                        </div>
                    @elseif(\["name"] === "stripe")
                        <div class="form-group"><label class="form-label">Publishable Key</label><input type="text" name="settings[publishable_key]" value="{{ \["publishable_key"] ?? "" }}" class="form-control" placeholder="pk_live_..."></div>
                        <div class="form-group"><label class="form-label">Secret Key</label><input type="password" name="settings[secret_key]" value="{{ \["secret_key"] ?? "" }}" class="form-control" placeholder="sk_live_..."></div>
                        <div class="form-group"><label class="form-label">Webhook Secret</label><input type="password" name="settings[webhook_secret]" value="{{ \["webhook_secret"] ?? "" }}" class="form-control" placeholder="whsec_..."></div>
                    @elseif(\["name"] === "coingate")
                        <div class="form-group"><label class="form-label">API Token</label><input type="password" name="settings[api_token]" value="{{ \["api_token"] ?? "" }}" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Environment</label>
                            <select name="settings[sandbox]" class="form-control">
                                <option value="0" {{ (\["sandbox"] ?? "0") == "0" ? "selected" : "" }}>Live</option>
                                <option value="1" {{ (\["sandbox"] ?? "0") == "1" ? "selected" : "" }}>Sandbox</option>
                            </select>
                        </div>
                    @else
                        <div class="form-group"><label class="form-label">Configuration (JSON)</label><textarea name="settings_json" rows="4" class="form-control" placeholder='{"key":"value"}'>{{ !empty(\) ? json_encode(\, JSON_PRETTY_PRINT) : "" }}</textarea></div>
                    @endif
                </div>
                <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" onclick="this.closest('[id]').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@endsection
