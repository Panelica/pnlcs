@extends("client.layouts.app")
@section("title", __("client.domain_search.title"))
@section("content")
<div class="pn-page-header"><div><h1 class="pn-page-title">{{ __('client.domain_search.heading') }}</h1><p class="pn-page-subtitle">{{ __('client.domain_search.subtitle') }}</p></div></div>
<div style="max-width:100%;padding:0;">

    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-size:32px;font-weight:800;color:#1a4d80;margin-bottom:8px;">{{ __('client.domain_search.find_perfect') }}</h1>
        <p style="color:var(--muted);font-size:16px;">{{ __('client.domain_search.search_desc') }}</p>
    </div>

    <form method="GET" action="{{ route('client.domain.search') }}" id="domain-search-form" style="margin-bottom:32px;">
        <div style="display:flex;background:var(--card);border:2px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <input type="text" name="domain" id="domain-input"
                value="{{ $searchDomain ? explode('.', $searchDomain)[0] : old('domain') }}"
                placeholder="{{ __('client.domain_search.placeholder') }}" required
                style="flex:1;border:none;outline:none;padding:16px 20px;font-size:18px;font-family:inherit;color:var(--text);background:transparent;min-width:0;">
            <div style="display:flex;align-items:center;border-left:1px solid var(--border);">
                <select name="tld" id="tld-select" style="border:none;outline:none;font-size:16px;font-weight:600;color:#1a4d80;background:transparent;padding:0 12px;cursor:pointer;font-family:inherit;height:58px;">
                    @foreach($tlds->whereIn('extension', ['.com','.net','.org','.io','.dev','.app','.co','.ai','.me','.xyz']) as $t)
                    <option value="{{ $t->extension }}" {{ ($results['tld'] ?? '.com') === $t->extension ? 'selected' : '' }}>{{ $t->extension }}</option>
                    @endforeach
                    <optgroup label="{{ __('client.domain_search.more_tlds') }}">
                        @foreach($tlds->reject(fn($t) => in_array($t->extension, ['.com','.net','.org','.io','.dev','.app','.co','.ai','.me','.xyz'])) as $t)
                        <option value="{{ $t->extension }}" {{ ($results['tld'] ?? '') === $t->extension ? 'selected' : '' }}>{{ $t->extension }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <button type="submit" style="padding:0 32px;background:#1a4d80;color:#fff;font-size:16px;font-weight:700;border:none;cursor:pointer;font-family:inherit;white-space:nowrap;">{{ __('common.actions.search') }}</button>
        </div>
    </form>

    @if($results)
    @php $primary = $results['primary']; @endphp
    @if($primary)
    <div style="background:var(--card);border-radius:12px;border:2px solid {{ ($primary['checked'] ?? true) ? ($primary['available'] ? '#22c55e' : '#ef4444') : '#f59e0b' }};padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:{{ ($primary['checked'] ?? true) ? ($primary['available'] ? '#dcfce7' : '#fee2e2') : '#fef3c7' }};display:flex;align-items:center;justify-content:center;">
                @if($primary['available'] && ($primary['checked'] ?? true))
                <svg width="22" height="22" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @else
                <svg width="22" height="22" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                @endif
            </div>
            <div>
                <div style="font-size:22px;font-weight:700;color:var(--text);">{{ $primary['domain'] }}</div>
                @php $primaryChecked = $primary['checked'] ?? true; @endphp
                <div style="font-size:14px;color:{{ ! $primaryChecked ? '#b45309' : ($primary['available'] ? '#16a34a' : '#dc2626') }};font-weight:600;">
                    @if(! $primaryChecked)
                        {{ __('client.domain_search.could_not_check') }}
                    @else
                        {{ $primary['available'] ? __('client.domain_search.available') : __('client.domain_search.already_registered') }}
                    @endif
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:20px;">
            @if($primary['available'] && ($primary['checked'] ?? true))
            <div style="text-align:right;">
                <div style="font-size:24px;font-weight:800;color:#1a4d80;">{{ money_fmt($primary['price']) }}<span style="font-size:13px;font-weight:400;color:var(--muted);">/yr</span></div>
                <div style="font-size:11px;color:var(--muted);">{{ __('client.domain_search.renews_at') }} {{ money_fmt($primary['renew_price']) }}/yr</div>
            </div>
            <form method="POST" action="{{ route('client.cart.add-domain') }}">
                @csrf
                <input type="hidden" name="domain" value="{{ $primary['domain'] }}">
                <input type="hidden" name="type" value="register">
                <input type="hidden" name="years" value="1">
                <button type="submit" style="padding:12px 24px;background:#06d6a0;color:var(--text);font-weight:700;font-size:15px;border:none;border-radius:8px;cursor:pointer;font-family:inherit;">{{ __('common.actions.add_to_cart') }}</button>
            </form>
            @else
            <a href="{{ route('client.domains.transfer', ['domain' => $primary['domain']]) }}" style="padding:12px 24px;background:#64748b;color:#fff;font-weight:700;font-size:15px;border:none;border-radius:8px;cursor:pointer;font-family:inherit;text-decoration:none;">{{ __('client.domains.transfer_domain') }}</a>
            @endif
        </div>
    </div>
    @endif

    @if(!empty($results['alternatives']))
    <div style="margin-bottom:40px;">
        <h3 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:16px;">{{ __('client.domain_search.other_options') }}</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
            @foreach($results['alternatives'] as $alt)
            @if($alt)
            <div style="background:var(--card);border:1px solid {{ $alt['available'] ? '#bbf7d0' : '#fecaca' }};border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ ($alt['checked'] ?? true) ? ($alt['available'] ? '#dcfce7' : '#fee2e2') : '#fef3c7' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        @if($alt['available'] && ($alt['checked'] ?? true))
                        <svg width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                        <svg width="14" height="14" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="font-weight:600;color:var(--text);font-size:15px;">{{ $alt['domain'] }}</div>
                        <div style="font-size:12px;color:{{ ! ($alt['checked'] ?? true) ? '#b45309' : ($alt['available'] ? '#16a34a' : '#dc2626') }};font-weight:600;">{{ ! ($alt['checked'] ?? true) ? __('client.domain_search.could_not_check_short') : ($alt['available'] ? __('client.domain_search.available_short') : __('client.domain_search.taken')) }}</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    @if($alt['available'] && ($alt['checked'] ?? true))
                    <div style="font-size:15px;font-weight:700;color:#1a4d80;">{{ money_fmt($alt['price']) }}<span style="font-size:11px;font-weight:400;color:var(--muted);">/yr</span></div>
                    <form method="POST" action="{{ route('client.cart.add-domain') }}">
                        @csrf
                        <input type="hidden" name="domain" value="{{ $alt['domain'] }}">
                        <input type="hidden" name="type" value="register">
                        <input type="hidden" name="years" value="1">
                        <button type="submit" style="padding:6px 14px;background:#1a4d80;color:#fff;font-size:12px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;">{{ __('common.actions.add') }}</button>
                    </form>
                    @else
                    <span style="font-size:13px;color:var(--muted);">{{ ($alt['checked'] ?? true) ? __('client.domain_search.taken') : __('client.domain_search.could_not_check_short') }}</span>
                    @endif
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif
    @endif

    <div style="background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:32px;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-size:20px;font-weight:700;color:var(--text);margin:0;">{{ __('client.nav.domain_pricing') }}</h2>
            <a href="{{ route('client.domain.pricing') }}" style="font-size:13px;color:#1a4d80;font-weight:600;text-decoration:none;">{{ __('client.domain_search.view_full_list') }} &rarr;</a>
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--bg);">
                    <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;">{{ __('client.domain_search.extension') }}</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;">{{ __('common.actions.register') }}</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;">{{ __('client.domain_search.transfer') }}</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;">{{ __('client.domain_search.renew') }}</th>
                    <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;">{{ __('client.security.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tlds->take(20) as $tld)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:10px 16px;font-family:monospace;font-weight:700;color:#1a4d80;font-size:15px;">{{ $tld->extension }}</td>
                    <td style="padding:10px 16px;text-align:center;font-weight:600;color:var(--text);">{{ money_fmt($tld->register_price) }}</td>
                    <td style="padding:10px 16px;text-align:center;color:var(--muted);">{{ money_fmt($tld->transfer_price) }}</td>
                    <td style="padding:10px 16px;text-align:center;color:var(--muted);">{{ money_fmt($tld->renew_price) }}</td>
                    <td style="padding:10px 16px;text-align:center;">
                        <a href="{{ route('client.domain.search') }}?tld={{ $tld->extension }}" style="padding:5px 12px;background:#eff6ff;color:#1a4d80;font-size:12px;font-weight:600;border:1px solid #bfdbfe;border-radius:5px;text-decoration:none;">{{ __('common.actions.search') }}</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
