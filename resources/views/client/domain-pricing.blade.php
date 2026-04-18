@extends("client.layouts.app")
@section("title", __("client.domain_search.pricing_title"))
@section("content")
<div class="pn-page-header"><div><h1 class="pn-page-title">{{ __('client.nav.domain_pricing') }}</h1><p class="pn-page-subtitle">{{ __('client.domain_pricing.subtitle') }}</p></div></div>
<div style="max-width:100%;padding:0;">

    <div style="text-align:center;margin-bottom:40px;">
        <h1 style="font-size:32px;font-weight:800;color:#1a4d80;margin-bottom:8px;">{{ __('client.nav.domain_pricing') }}</h1>
        <p style="color:#64748b;font-size:16px;margin-bottom:24px;">{{ __('client.domain_pricing.transparent') }}</p>
        <a href="{{ route('client.domain.search') }}" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#1a4d80;color:#fff;font-weight:700;font-size:15px;border-radius:8px;text-decoration:none;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
            {{ __('client.domain_pricing.search_availability') }}
        </a>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;" id="category-tabs">
        <button onclick="filterTLDs('all')" id="tab-all" style="padding:8px 18px;background:#1a4d80;color:#fff;border:none;border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.all') }}</button>
        <button onclick="filterTLDs('popular')" id="tab-popular" style="padding:8px 18px;background:#fff;color:#64748b;border:1px solid #e2e8f0;border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.popular') }}</button>
        <button onclick="filterTLDs('country')" id="tab-country" style="padding:8px 18px;background:#fff;color:#64748b;border:1px solid #e2e8f0;border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.country_tab') }}</button>
        <button onclick="filterTLDs('new')" id="tab-new" style="padding:8px 18px;background:#fff;color:#64748b;border:1px solid #e2e8f0;border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.new_tlds') }}</button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <input type="text" id="tld-filter" placeholder="{{ __('client.domain_pricing.filter') }}" oninput="applyFilters()" style="border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:13px;outline:none;font-family:inherit;width:160px;">
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;" id="pricing-table">
            <thead>
                <tr style="background:#1a4d80;">
                    <th style="padding:12px 20px;text-align:left;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.extension') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('common.actions.register') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.transfer') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.renew') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_pricing.min_years') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.security.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                $popularTlds = ['.com','.net','.org','.io','.dev','.app','.co','.ai','.me','.tv','.cc','.biz','.info','.us'];
                $countryTlds = ['.de','.uk','.fr','.nl','.eu','.tr','.ru','.in','.ca','.au','.us'];
                $newTlds     = ['.xyz','.online','.site','.store','.tech','.space','.cloud','.host','.pro','.agency','.digital','.email','.solutions','.systems','.network','.studio','.design','.shop','.live','.world','.today','.media','.zone','.club','.life','.center'];
                @endphp
                @foreach($popular as $tld)
                @php
                $cats = [];
                if(in_array($tld->extension, $popularTlds)) $cats[] = 'popular';
                if(in_array($tld->extension, $countryTlds)) $cats[] = 'country';
                if(in_array($tld->extension, $newTlds)) $cats[] = 'new';
                if(empty($cats)) $cats[] = 'popular';
                @endphp
                <tr class="tld-row" data-cats="{{ implode(',', $cats) }}" data-ext="{{ $tld->extension }}" style="border-top:1px solid #f1f5f9;">
                    <td style="padding:12px 20px;">
                        <span style="font-family:monospace;font-size:15px;font-weight:700;color:#1a4d80;">{{ $tld->extension }}</span>
                        @if($tld->dns_management)<span style="margin-left:6px;font-size:10px;background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-weight:600;">DNS</span>@endif
                    </td>
                    <td style="padding:12px 20px;text-align:center;font-weight:700;color:#1e293b;font-size:15px;">${{ number_format($tld->register_price, 2) }}<span style="font-size:11px;color:#94a3b8;font-weight:400;">/yr</span></td>
                    <td style="padding:12px 20px;text-align:center;color:#64748b;">${{ number_format($tld->transfer_price, 2) }}</td>
                    <td style="padding:12px 20px;text-align:center;color:#64748b;">${{ number_format($tld->renew_price, 2) }}</td>
                    <td style="padding:12px 20px;text-align:center;color:#94a3b8;font-size:13px;">{{ $tld->min_years }}</td>
                    <td style="padding:12px 20px;text-align:center;">
                        <a href="{{ route('client.domain.search') }}?tld={{ $tld->extension }}" style="padding:6px 14px;background:#06d6a0;color:#0f172a;font-size:12px;font-weight:700;border-radius:6px;text-decoration:none;display:inline-block;">{{ __('common.actions.register') }}</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div id="no-results" style="display:none;text-align:center;padding:48px;color:#94a3b8;">{{ __('client.domain_pricing.no_results') }}</div>
    </div>

    <div style="margin-top:24px;padding:16px 20px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
        <p style="font-size:13px;color:#64748b;margin:0;"><strong style="color:#1e293b;">{{ __('client.domain_pricing.note') }}:</strong> {{ __('client.domain_pricing.note_text') }} <a href="{{ route('client.domain.search') }}" style="color:#1a4d80;font-weight:600;">{{ __('client.domain_pricing.search_link') }}</a> {{ __('client.domain_pricing.note_text_2') }}</p>
    </div>
</div>

<script>
var currentFilter = 'all';
function filterTLDs(cat) {
    currentFilter = cat;
    document.querySelectorAll('#category-tabs button').forEach(function(btn) {
        btn.style.background = '#fff'; btn.style.color = '#64748b'; btn.style.border = '1px solid #e2e8f0';
    });
    var ab = document.getElementById('tab-' + cat);
    if(ab) { ab.style.background = '#1a4d80'; ab.style.color = '#fff'; ab.style.border = '1px solid #1a4d80'; }
    applyFilters();
}
function applyFilters() {
    var tv = document.getElementById('tld-filter').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.tld-row');
    var vis = 0;
    rows.forEach(function(row) {
        var cats = row.dataset.cats ? row.dataset.cats.split(',') : [];
        var ext = row.dataset.ext || '';
        var cm = (currentFilter === 'all' || cats.indexOf(currentFilter) !== -1);
        var tm = !tv || ext.indexOf(tv) !== -1;
        if(cm && tm) { row.style.display = ''; vis++; } else { row.style.display = 'none'; }
    });
    document.getElementById('no-results').style.display = vis === 0 ? 'block' : 'none';
}
</script>
@endsection
