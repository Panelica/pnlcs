@extends("admin.layouts.app")
@section("title", "WHOIS Lookup")
@section("content")

<div class="page-header">
    <h1><i class="fas fa-search" style="margin-right:8px;color:#1a4d80;"></i> WHOIS Lookup</h1>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="font-weight:600;">Domain WHOIS Lookup</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.whois.lookup') }}">
            @csrf
            @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;">{{ $errors->first() }}</div>
            @endif
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:240px;margin:0;">
                    <label class="form-label">Domain Name</label>
                    <input type="text" name="domain" value="{{ $domain ?? '' }}" class="form-control"
                        placeholder="e.g. google.com, github.io" required autofocus
                        style="font-family:monospace;font-size:15px;">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Lookup
                </button>
            </div>
            <div style="margin-top:8px;font-size:12px;color:#94a3b8;">
                Supports 50+ TLDs: .com, .net, .org, .io, .dev, .app, .ai, .me, country codes, and more.
            </div>
        </form>
    </div>
</div>

@if($result)

@if(!empty($result['parsed']))
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-weight:600;"><i class="fas fa-info-circle" style="margin-right:6px;color:#1a4d80;"></i> Domain Information: {{ $result['domain'] }}</span>
        @if($result['available'] ?? false)
        <span class="badge-active">Available</span>
        @else
        <span class="badge-suspended">Registered</span>
        @endif
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
            @foreach($result['parsed'] as $label => $value)
            <div style="background:#f8fafc;border-radius:8px;padding:12px 14px;border:1px solid #e2e8f0;">
                <div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">{{ $label }}</div>
                @if(is_array($value))
                    @foreach($value as $v)
                    <div style="font-size:13px;color:#1e293b;font-family:monospace;">{{ $v }}</div>
                    @endforeach
                @else
                <div style="font-size:13px;color:#1e293b;font-family:monospace;">{{ $value }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-weight:600;"><i class="fas fa-terminal" style="margin-right:6px;color:#64748b;"></i> Raw WHOIS Response</span>
        <span style="font-size:12px;color:#94a3b8;font-family:monospace;">{{ $result['server'] ?? '' }}</span>
    </div>
    <div class="card-body" style="padding:0;">
        <pre style="margin:0;padding:20px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;color:#334155;background:#f8fafc;white-space:pre-wrap;word-break:break-all;max-height:600px;overflow-y:auto;">{{ $result['raw'] }}</pre>
    </div>
</div>

@else

<div class="card" style="border:1px dashed #e2e8f0;background:#fafafa;">
    <div class="card-body" style="text-align:center;padding:48px 24px;">
        <i class="fas fa-globe" style="font-size:48px;color:#cbd5e1;margin-bottom:16px;display:block;"></i>
        <h3 style="font-size:18px;font-weight:700;color:#1e293b;margin-bottom:8px;">WHOIS Domain Lookup</h3>
        <p style="color:#64748b;font-size:14px;max-width:400px;margin:0 auto 16px;">
            Enter any domain name above to see its WHOIS record — registrar, expiry date, nameservers, and more.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
            @foreach(['google.com','github.io','cloudflare.com','stripe.com','panelica.com'] as $eg)
            <button onclick="document.querySelector('[name=domain]').value='{{ $eg }}';"
                style="padding:6px 14px;background:#eff6ff;color:#1a4d80;font-size:12px;font-weight:600;border:1px solid #bfdbfe;border-radius:6px;cursor:pointer;font-family:inherit;">{{ $eg }}</button>
            @endforeach
        </div>
    </div>
</div>

@endif

@endsection
