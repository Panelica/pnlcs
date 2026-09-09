@extends("client.layouts.app")
@section("title", __("client.domain_search.pricing_title"))
@section("content")
<div class="pn-page-header"><div><h1 class="pn-page-title">{{ __('client.nav.domain_pricing') }}</h1><p class="pn-page-subtitle">{{ __('client.domain_pricing.subtitle') }}</p></div></div>
<div style="max-width:100%;padding:0;">

    <div style="text-align:center;margin-bottom:40px;">
        <h1 style="font-size:32px;font-weight:800;color:#1a4d80;margin-bottom:8px;">{{ __('client.nav.domain_pricing') }}</h1>
        <p style="color:var(--muted);font-size:16px;margin-bottom:24px;">{{ __('client.domain_pricing.transparent') }}</p>
        <a href="{{ route('client.domain.search') }}" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#1a4d80;color:#fff;font-weight:700;font-size:15px;border-radius:8px;text-decoration:none;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
            {{ __('client.domain_pricing.search_availability') }}
        </a>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;" id="category-tabs">
        <button onclick="filterTLDs('all')" id="tab-all" style="padding:8px 18px;background:#1a4d80;color:#fff;border:none;border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.all') }}</button>
        <button onclick="filterTLDs('popular')" id="tab-popular" style="padding:8px 18px;background:var(--card);color:var(--muted);border:1px solid var(--border);border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.popular') }}</button>
        <button onclick="filterTLDs('generic')" id="tab-generic" style="padding:8px 18px;background:var(--card);color:var(--muted);border:1px solid var(--border);border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.generic') }}</button>
        <button onclick="filterTLDs('local')" id="tab-local" style="padding:8px 18px;background:var(--card);color:var(--muted);border:1px solid var(--border);border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.local_tlds') }}</button>
        <button onclick="filterTLDs('country')" id="tab-country" style="padding:8px 18px;background:var(--card);color:var(--muted);border:1px solid var(--border);border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.country_tab') }}</button>
        <button onclick="filterTLDs('new')" id="tab-new" style="padding:8px 18px;background:var(--card);color:var(--muted);border:1px solid var(--border);border-radius:20px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">{{ __('client.domain_pricing.new_tlds') }}</button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <input type="text" id="tld-filter" placeholder="{{ __('client.domain_pricing.filter') }}" oninput="applyFilters()" style="border:1px solid var(--border);border-radius:6px;padding:6px 10px;font-size:13px;outline:none;font-family:inherit;width:160px;">
        </div>
    </div>

    <div id="local-warning" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #b91c1c;border-radius:8px;padding:14px 18px;margin-bottom:16px;">
        <div style="display:flex;gap:10px;align-items:flex-start;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div style="color:#b91c1c;font-size:13px;font-weight:600;line-height:1.6;">{{ __("client.domain_search.local_warning") }}</div>
        </div>
    </div>

    <div style="background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;" id="pricing-table">
            <thead>
                <tr style="background:#1a4d80;">
                    <th style="padding:12px 20px;text-align:left;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.extension') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('common.actions.register') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.transfer') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.renew') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.grace') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_search.restore') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.domain_pricing.min_years') }}</th>
                    <th style="padding:12px 20px;text-align:center;color:#fff;font-size:13px;font-weight:600;">{{ __('client.security.action') }}</th>
                </tr>
            </thead>
            <tbody>
                                @foreach($popular as $tld)
                                <tr class="tld-row" data-cats="{{ $tld->category }}{{ $tld->is_popular ? ',popular' : '' }}" data-ext="{{ $tld->extension }}" style="border-top:1px solid var(--border);">
                    <td style="padding:12px 20px;">
                        <span style="font-family:monospace;font-size:15px;font-weight:700;color:#1a4d80;">{{ $tld->extension }}</span>
                        @if($tld->dns_management)<span style="margin-left:6px;font-size:10px;background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-weight:600;">DNS</span>@endif
                    </td>
                    <td style="padding:12px 20px;text-align:center;font-weight:700;color:var(--text);font-size:15px;">{{ domain_money_fmt($tld->register_price) }}<span style="font-size:11px;color:var(--muted);font-weight:400;">/{{ __('client.domain_search.per_year') }}</span></td>
                    <td style="padding:12px 20px;text-align:center;color:var(--muted);">{{ domain_money_fmt($tld->transfer_price) }}</td>
                    <td style="padding:12px 20px;text-align:center;color:var(--muted);">{{ domain_money_fmt($tld->renew_price) }}</td>
                    <td style="padding:12px 20px;text-align:center;color:var(--muted);font-size:13px;">{{ $tld->grace_period > 0 ? $tld->grace_period . ' ' . __('client.domain_search.days') : '-' }}</td>
                    <td style="padding:12px 20px;text-align:center;color:var(--muted);">@if($tld->restore_price > 0){{ domain_money_fmt($tld->restore_price) }}@else<span title="{{ __("client.domain_search.no_restore_title") }}" style="display:inline-block;padding:2px 8px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;">{{ __("client.domain_search.no_restore") }}</span>@endif</td>
                    <td style="padding:12px 20px;text-align:center;color:var(--muted);font-size:13px;">{{ $tld->min_years }}</td>
                    <td style="padding:12px 20px;text-align:center;">
                        <a href="{{ route('client.domain.search') }}?tld={{ $tld->extension }}" style="padding:6px 14px;background:#06d6a0;color:var(--text);font-size:12px;font-weight:700;border-radius:6px;text-decoration:none;display:inline-block;">{{ __('common.actions.register') }}</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div id="no-results" style="display:none;text-align:center;padding:48px;color:var(--muted);">{{ __('client.domain_pricing.no_results') }}</div>
    </div>

    <div style="margin-top:24px;padding:16px 20px;background:var(--bg);border-radius:8px;border:1px solid var(--border);">
        <p style="font-size:13px;color:var(--muted);margin:0;"><strong style="color:var(--text);">{{ __('client.domain_pricing.note') }}:</strong> {{ __('client.domain_pricing.note_text') }} <a href="{{ route('client.domain.search') }}" style="color:#1a4d80;font-weight:600;">{{ __('client.domain_pricing.search_link') }}</a> {{ __('client.domain_pricing.note_text_2') }}</p>
    </div>
</div>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-top:20px;">
        <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:10px;">{{ __("client.domain_search.legend_title") }}</div>
        <ul style="margin:0;padding-left:18px;color:var(--muted);font-size:13px;line-height:1.7;">
            <li>{{ __("client.domain_search.legend_grace") }}</li>
            <li>{{ __("client.domain_search.legend_restore") }}</li>
            <li style="color:var(--text);"><strong>{{ __("client.domain_search.legend_local") }}</strong></li>
            <li style="color:var(--text);"><strong>{{ __("client.domain_search.legend_data") }}</strong></li>
            <li>{{ __("client.domain_search.legend_notice") }}</li>
        </ul>
    </div>


<script>
var currentFilter = 'all';
function filterTLDs(cat) {
    currentFilter = cat;
    document.querySelectorAll('#category-tabs button').forEach(function(btn) {
        btn.style.background = 'var(--card)'; btn.style.color = 'var(--muted)'; btn.style.border = '1px solid var(--border)';
    });
    var ab = document.getElementById('tab-' + cat);
    if(ab) { ab.style.background = '#1a4d80'; ab.style.color = '#fff'; ab.style.border = '1px solid #1a4d80'; }
    var w = document.getElementById('local-warning');
    if (w) w.style.display = (cat === 'local') ? 'block' : 'none';
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
