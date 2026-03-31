@extends("client.layouts.app")
@section("title", "Domain Search")
@section("content")
<div style="max-width:900px;margin:0 auto;padding:24px 16px;">

    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-size:32px;font-weight:800;color:#1a4d80;margin-bottom:8px;">Find Your Perfect Domain</h1>
        <p style="color:#64748b;font-size:16px;">Search availability and register or transfer in seconds</p>
    </div>

    <form method="GET" action="{{ route('client.domain.search') }}" id="domain-search-form" style="margin-bottom:32px;">
        <div style="display:flex;background:#fff;border:2px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <input type="text" name="domain" id="domain-input"
                value="{{ $searchDomain ? explode('.', $searchDomain)[0] : old('domain') }}"
                placeholder="Enter domain name (e.g. mysite)" required
                style="flex:1;border:none;outline:none;padding:16px 20px;font-size:18px;font-family:inherit;color:#1e293b;background:transparent;min-width:0;">
            <div style="display:flex;align-items:center;border-left:1px solid #e2e8f0;">
                <select name="tld" id="tld-select" style="border:none;outline:none;font-size:16px;font-weight:600;color:#1a4d80;background:transparent;padding:0 12px;cursor:pointer;font-family:inherit;height:58px;">
                    @foreach($tlds->whereIn('extension', ['.com','.net','.org','.io','.dev','.app','.co','.ai','.me','.xyz']) as $t)
                    <option value="{{ $t->extension }}" {{ ($results['tld'] ?? '.com') === $t->extension ? 'selected' : '' }}>{{ $t->extension }}</option>
                    @endforeach
                    <optgroup label="More TLDs">
                        @foreach($tlds->reject(fn($t) => in_array($t->extension, ['.com','.net','.org','.io','.dev','.app','.co','.ai','.me','.xyz'])) as $t)
                        <option value="{{ $t->extension }}" {{ ($results['tld'] ?? '') === $t->extension ? 'selected' : '' }}>{{ $t->extension }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <button type="submit" style="padding:0 32px;background:#1a4d80;color:#fff;font-size:16px;font-weight:700;border:none;cursor:pointer;font-family:inherit;white-space:nowrap;">Search</button>
        </div>
    </form>

    @if($results)
    @php $primary = $results['primary']; @endphp
    @if($primary)
    <div style="background:#fff;border-radius:12px;border:2px solid {{ $primary['available'] ? '#22c55e' : '#ef4444' }};padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:{{ $primary['available'] ? '#dcfce7' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;">
                @if($primary['available'])
                <svg width="22" height="22" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @else
                <svg width="22" height="22" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                @endif
            </div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#1e293b;">{{ $primary['domain'] }}</div>
                <div style="font-size:14px;color:{{ $primary['available'] ? '#16a34a' : '#dc2626' }};font-weight:600;">
                    {{ $primary['available'] ? 'Available!' : 'Already Registered' }}
                    @if($primary['whois_error'] ?? false)
                    <span style="color:#94a3b8;font-weight:400;font-size:12px;">(availability unconfirmed)</span>
                    @endif
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:20px;">
            @if($primary['available'])
            <div style="text-align:right;">
                <div style="font-size:24px;font-weight:800;color:#1a4d80;">${{ number_format($primary['price'], 2) }}<span style="font-size:13px;font-weight:400;color:#94a3b8;">/yr</span></div>
                <div style="font-size:11px;color:#94a3b8;">Renews at ${{ number_format($primary['renew_price'], 2) }}/yr</div>
            </div>
            <form method="POST" action="{{ route('client.cart.add-domain') }}">
                @csrf
                <input type="hidden" name="domain" value="{{ $primary['domain'] }}">
                <input type="hidden" name="type" value="register">
                <input type="hidden" name="years" value="1">
                <button type="submit" style="padding:12px 24px;background:#06d6a0;color:#0f172a;font-weight:700;font-size:15px;border:none;border-radius:8px;cursor:pointer;font-family:inherit;">Add to Cart</button>
            </form>
            @else
            <form method="POST" action="{{ route('client.cart.add-domain') }}">
                @csrf
                <input type="hidden" name="domain" value="{{ $primary['domain'] }}">
                <input type="hidden" name="type" value="transfer">
                <input type="hidden" name="years" value="1">
                <button type="submit" style="padding:12px 24px;background:#64748b;color:#fff;font-weight:700;font-size:15px;border:none;border-radius:8px;cursor:pointer;font-family:inherit;">Transfer Domain</button>
            </form>
            @endif
        </div>
    </div>
    @endif

    @if(!empty($results['alternatives']))
    <div style="margin-bottom:40px;">
        <h3 style="font-size:18px;font-weight:700;color:#1e293b;margin-bottom:16px;">Other Options</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
            @foreach($results['alternatives'] as $alt)
            @if($alt)
            <div style="background:#fff;border:1px solid {{ $alt['available'] ? '#bbf7d0' : '#fecaca' }};border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $alt['available'] ? '#dcfce7' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        @if($alt['available'])
                        <svg width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                        <svg width="14" height="14" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="font-weight:600;color:#1e293b;font-size:15px;">{{ $alt['domain'] }}</div>
                        <div style="font-size:12px;color:{{ $alt['available'] ? '#16a34a' : '#dc2626' }};font-weight:600;">{{ $alt['available'] ? 'Available' : 'Taken' }}</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    @if($alt['available'])
                    <div style="font-size:15px;font-weight:700;color:#1a4d80;">${{ number_format($alt['price'], 2) }}<span style="font-size:11px;font-weight:400;color:#94a3b8;">/yr</span></div>
                    <form method="POST" action="{{ route('client.cart.add-domain') }}">
                        @csrf
                        <input type="hidden" name="domain" value="{{ $alt['domain'] }}">
                        <input type="hidden" name="type" value="register">
                        <input type="hidden" name="years" value="1">
                        <button type="submit" style="padding:6px 14px;background:#1a4d80;color:#fff;font-size:12px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;">Add</button>
                    </form>
                    @else
                    <span style="font-size:13px;color:#94a3b8;">Taken</span>
                    @endif
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif
    @endif

    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:32px;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin:0;">Domain Pricing</h2>
            <a href="{{ route('client.domain.pricing') }}" style="font-size:13px;color:#1a4d80;font-weight:600;text-decoration:none;">View Full List &rarr;</a>
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Extension</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Register</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Transfer</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Renew</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tlds->take(20) as $tld)
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:10px 16px;font-family:monospace;font-weight:700;color:#1a4d80;font-size:15px;">{{ $tld->extension }}</td>
                    <td style="padding:10px 16px;text-align:center;font-weight:600;color:#1e293b;">${{ number_format($tld->register_price, 2) }}</td>
                    <td style="padding:10px 16px;text-align:center;color:#64748b;">${{ number_format($tld->transfer_price, 2) }}</td>
                    <td style="padding:10px 16px;text-align:center;color:#64748b;">${{ number_format($tld->renew_price, 2) }}</td>
                    <td style="padding:10px 16px;text-align:center;">
                        <a href="{{ route('client.domain.search') }}?tld={{ $tld->extension }}" style="padding:5px 12px;background:#eff6ff;color:#1a4d80;font-size:12px;font-weight:600;border:1px solid #bfdbfe;border-radius:5px;text-decoration:none;">Search</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
