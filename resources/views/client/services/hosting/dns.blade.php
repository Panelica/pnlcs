@extends("client.layouts.app")
@section("title", __('client.hosting.dns.title'))
@section("content")

@php($selectedName = $domains[$selected] ?? '')

<style>
    .dz-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .dz-back:hover{color:var(--primary)}
    .dz-head{display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .dz-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(99,102,241,.6)}
    .dz-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .dz-head .sub{font-size:13px;color:var(--muted)}
    .dz-zones{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
    .dz-zone{display:inline-flex;align-items:center;gap:7px;padding:8px 15px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-size:13px;font-weight:600}
    .dz-zone:hover{border-color:var(--primary);color:var(--primary)}
    .dz-zone.on{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 6px 14px -6px rgba(99,102,241,.7)}
    .dz-zone.on .dz-badge{background:rgba(255,255,255,.25);color:#fff}
    .dz-badge{font-size:11px;font-weight:800;padding:1px 7px;border-radius:999px;background:var(--bg);color:var(--muted)}
    .dz-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .dz-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .dz-ch .dz-of{margin-left:auto;font-size:12px;font-weight:600;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .dz-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .dz-fld{flex:1;min-width:120px}
    .dz-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .dz-inp,.dz-sel{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px;box-sizing:border-box}
    .dz-suffix{display:flex;align-items:center}
    .dz-suffix .dz-inp{border-top-right-radius:0;border-bottom-right-radius:0}
    .dz-suffix span{padding:9px 10px;border:1px solid var(--border);border-left:none;border-top-right-radius:9px;border-bottom-right-radius:9px;background:var(--bg);color:var(--muted);font-size:12px;font-family:ui-monospace,Menlo,monospace;white-space:nowrap}
    .dz-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .dz-btn:hover{background:var(--primary-dark)}
    .dz-hint{font-size:11.5px;color:var(--muted);padding:0 18px 16px;display:flex;align-items:center;gap:6px}
    .dz-table{width:100%;border-collapse:collapse}
    .dz-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .dz-table tbody td{padding:11px 18px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)}
    .dz-table tbody tr:last-child td{border-bottom:none}
    .dz-table tbody tr:hover{background:var(--primary-light)}
    .dz-type{font-size:11px;font-weight:800;padding:3px 9px;border-radius:6px;background:rgba(99,102,241,.12);color:#4f46e5;letter-spacing:.3px}
    .dz-name{font-weight:600;font-family:ui-monospace,Menlo,monospace;font-size:12.5px}
    .dz-val{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--muted);max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .dz-lock{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted);display:inline-flex;align-items:center;gap:4px}
    .dz-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid transparent;color:var(--muted);cursor:pointer;background:transparent}
    .dz-act:hover{background:rgba(239,68,68,.1);color:#dc2626}
    .dz-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="dz-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="dz-head">
    <div class="dz-head-ic"><i class="ri-global-line"></i></div>
    <div><h1>{{ __('client.hosting.dns.title') }}</h1><div class="sub">{{ __('client.hosting.dns.subtitle') }}</div></div>
</div>

@if(empty($domains))
<div class="dz-card"><div class="dz-empty">{{ __('client.hosting.dns.no_domains') }}</div></div>
@else

@if(count($domains) > 1)
<div class="dz-zones">
    @foreach($domains as $id => $name)
    <a href="{{ route('client.services.dns', [$service, 'domain' => $id]) }}" class="dz-zone {{ $id === $selected ? 'on' : '' }}">
        <i class="ri-global-line" style="font-size:14px"></i>{{ $name }}
        @if($id === $selected)<span class="dz-badge">{{ count($records) }}</span>@endif
    </a>
    @endforeach
</div>
@endif

<div class="dz-card">
    <div class="dz-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.dns.create_title') }}<span class="dz-of">{{ $selectedName }}</span></div>
    <form method="POST" action="{{ route('client.services.dns.store', $service) }}" class="dz-form">
        @csrf
        <input type="hidden" name="domain_id" value="{{ $selected }}">
        <div class="dz-fld" style="max-width:110px"><label class="dz-lbl">{{ __('client.hosting.dns.type') }}</label>
            <select name="type" id="dz-type" required class="dz-sel" onchange="dzType()">@foreach($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
        </div>
        <div class="dz-fld" style="max-width:250px"><label class="dz-lbl">{{ __('client.hosting.dns.name') }}</label>
            <div class="dz-suffix">
                <input type="text" name="name" value="@" maxlength="255" class="dz-inp" placeholder="@">
                <span>.{{ $selectedName }}</span>
            </div>
        </div>
        <div class="dz-fld" style="min-width:210px"><label class="dz-lbl">{{ __('client.hosting.dns.value') }}</label>
            <input type="text" name="content" id="dz-content" required maxlength="1024" class="dz-inp" placeholder="203.0.113.10">
        </div>
        <div class="dz-fld" style="max-width:95px" id="dz-prio-wrap" hidden><label class="dz-lbl">{{ __('client.hosting.dns.priority') }}</label>
            <input type="number" name="priority" value="10" min="0" max="65535" class="dz-inp">
        </div>
        <div class="dz-fld" style="max-width:100px"><label class="dz-lbl">{{ __('client.hosting.dns.ttl') }}</label>
            <input type="number" name="ttl" value="3600" min="60" max="604800" class="dz-inp">
        </div>
        <button type="submit" class="dz-btn"><i class="ri-add-line"></i>{{ __('client.hosting.dns.create') }}</button>
    </form>
    <div class="dz-hint"><i class="ri-information-line"></i>{{ __('client.hosting.dns.name_hint') }}</div>
</div>

<div class="dz-card">
    <div class="dz-ch"><i class="ri-list-check-2"></i>{{ __('client.hosting.dns.records_of') }}<span class="dz-of">{{ $selectedName }} · {{ count($records) }}</span></div>
    @if(empty($records))
    <div class="dz-empty">{{ __('client.hosting.dns.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="dz-table">
        <thead><tr>
            <th style="width:80px">{{ __('client.hosting.dns.type') }}</th>
            <th>{{ __('client.hosting.dns.name') }}</th>
            <th>{{ __('client.hosting.dns.value') }}</th>
            <th style="width:80px">{{ __('client.hosting.dns.ttl') }}</th>
            <th style="width:90px;text-align:right">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
            @foreach($records as $r)
            <tr>
                <td><span class="dz-type">{{ $r['type'] }}</span></td>
                <td><span class="dz-name">{{ $r['name'] === '@' || $r['name'] === '' ? $r['domain'] : $r['name'].'.'.$r['domain'] }}</span></td>
                <td><div class="dz-val" title="{{ $r['content'] }}">@if($r['priority'] !== null)<b>{{ $r['priority'] }}</b> @endif{{ $r['content'] }}</div></td>
                <td class="dz-val">{{ $r['ttl'] ?? '—' }}</td>
                <td style="text-align:right">
                    @if($r['protected'])
                        <span class="dz-lock" title="{{ __('client.hosting.dns.protected_hint') }}"><i class="ri-lock-line" style="font-size:10px"></i>{{ __('client.hosting.dns.protected') }}</span>
                    @else
                    <form method="POST" action="{{ route('client.services.dns.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.dns.delete_confirm') }}')">
                        @csrf<input type="hidden" name="record_id" value="{{ $r['id'] }}">
                        <button type="submit" class="dz-act" title="{{ __('client.hosting.dns.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endif

<script>
// MX/SRV carry a priority; nothing else does. The value placeholder follows the
// record type so the customer is not guessing the format.
var DZ_PH = {A:'203.0.113.10', AAAA:'2001:db8::1', CNAME:'target.example.com.', MX:'mail.example.com.', TXT:'v=spf1 include:example.com ~all', SRV:'10 5 5060 sip.example.com.', CAA:'0 issue "letsencrypt.org"'};
function dzType(){
    var t = document.getElementById('dz-type').value;
    document.getElementById('dz-prio-wrap').hidden = !(t === 'MX' || t === 'SRV');
    var c = document.getElementById('dz-content');
    if (c) c.placeholder = DZ_PH[t] || '';
}
document.addEventListener('DOMContentLoaded', dzType);
</script>

@endsection
