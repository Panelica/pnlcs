@extends("client.layouts.app")
@section("title", __('client.hosting.subdomains.title'))
@section("content")

<style>
    .sd-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .sd-back:hover{color:var(--primary)}
    .sd-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .sd-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(16,185,129,.6)}
    .sd-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .sd-head .sub{font-size:13px;color:var(--muted)}
    .sd-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .sd-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .sd-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .sd-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .sd-fld{flex:1;min-width:130px}
    .sd-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .sd-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px}
    .sd-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .sd-btn:hover{background:var(--primary-dark)}
    .sd-note{padding:16px 18px;display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--muted)}
    .sd-note i{font-size:18px;color:#f59e0b}
    .sd-table{width:100%;border-collapse:collapse}
    .sd-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .sd-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text)}
    .sd-table tbody tr:last-child td{border-bottom:none}
    .sd-table tbody tr:hover{background:var(--primary-light)}
    .sd-name{display:inline-flex;align-items:center;gap:9px;font-weight:600}
    .sd-name .ic{width:30px;height:30px;border-radius:8px;background:rgba(16,185,129,.14);color:#059669;display:flex;align-items:center;justify-content:center;font-size:15px}
    .sd-name a{color:var(--text);text-decoration:none}.sd-name a:hover{color:var(--primary)}
    .sd-meta{font-size:12px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .sd-tag{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted)}
    .sd-ssl{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669}
    .sd-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid transparent;color:var(--muted);cursor:pointer;background:transparent}
    .sd-act:hover{background:rgba(239,68,68,.1);color:#dc2626}
    .sd-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
    .sd-check{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text);padding-bottom:9px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="sd-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="sd-head">
    <div class="sd-head-ic"><i class="ri-node-tree"></i></div>
    <div><h1>{{ __('client.hosting.subdomains.title') }}</h1><div class="sub">{{ __('client.hosting.subdomains.subtitle') }}</div></div>
    <span class="sd-cnt">{{ $policy['max'] < 0 ? $policy['used'].' / ∞' : $policy['used'].' / '.$policy['max'] }}</span>
</div>

@if(empty($domains))
<div class="sd-card"><div class="sd-empty">{{ __('client.hosting.subdomains.no_domains') }}</div></div>
@else
<div class="sd-card">
    <div class="sd-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.subdomains.create_title') }}</div>
    @if(! $policy['can_create'])
        <div class="sd-note"><i class="ri-error-warning-line"></i>{{ __('client.hosting.subdomains.limit_reached') }} ({{ $policy['used'] }}/{{ $policy['max'] }})</div>
    @else
    <form method="POST" action="{{ route('client.services.subdomains.store', $service) }}" class="sd-form">
        @csrf
        <div class="sd-fld" style="max-width:150px"><label class="sd-lbl">{{ __('client.hosting.subdomains.name') }}</label><input type="text" name="name" required maxlength="63" pattern="[A-Za-z0-9-]+" class="sd-inp" placeholder="blog"></div>
        <div style="padding-bottom:9px;color:var(--muted);font-size:18px">.</div>
        <div class="sd-fld"><label class="sd-lbl">{{ __('client.hosting.subdomains.domain') }}</label>
            <select name="domain_id" required class="sd-inp">@foreach($domains as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
        <div class="sd-fld"><label class="sd-lbl">{{ __('client.hosting.subdomains.document_root') }}</label><input type="text" name="document_root" maxlength="255" class="sd-inp" placeholder="public_html"></div>
        <label class="sd-check"><input type="checkbox" name="ssl" value="1" checked> {{ __('client.hosting.subdomains.ssl') }}</label>
        <button type="submit" class="sd-btn"><i class="ri-add-line"></i>{{ __('client.hosting.subdomains.create') }}</button>
    </form>
    @endif
</div>

<div class="sd-card">
    <div class="sd-ch"><i class="ri-node-tree"></i>{{ __('client.hosting.subdomains.title') }}</div>
    @if(empty($subdomains))
    <div class="sd-empty">{{ __('client.hosting.subdomains.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="sd-table">
        <thead><tr><th>{{ __('client.hosting.subdomains.full_name') }}</th><th>{{ __('client.hosting.subdomains.document_root') }}</th><th>{{ __('client.hosting.subdomains.php') }}</th><th>SSL</th><th>{{ __('common.table.status') }}</th><th style="text-align:right">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
            @foreach($subdomains as $s)
            <tr>
                <td><span class="sd-name"><span class="ic"><i class="ri-global-line"></i></span><a href="https://{{ $s['full_name'] }}" target="_blank" rel="noopener">{{ $s['full_name'] }}</a></span></td>
                <td class="sd-meta">{{ $s['document_root'] ?: '—' }}</td>
                <td>@if($s['php_version'])<span class="sd-tag">PHP {{ $s['php_version'] }}</span>@else<span class="sd-meta">—</span>@endif</td>
                <td>@if($s['ssl'])<span class="sd-ssl"><i class="ri-lock-line" style="font-size:10px"></i> SSL</span>@else<span class="sd-meta">—</span>@endif</td>
                <td><span class="sd-tag" style="{{ strtolower($s['status'])==='active'?'background:rgba(16,185,129,.12);color:#059669;border-color:transparent':'' }}">{{ $s['status'] ?: 'active' }}</span></td>
                <td style="text-align:right">
                    <form method="POST" action="{{ route('client.services.subdomains.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.subdomains.delete_confirm') }}')">
                        @csrf<input type="hidden" name="subdomain_id" value="{{ $s['id'] }}">
                        <button type="submit" class="sd-act" title="{{ __('client.hosting.subdomains.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endif

@endsection
