@extends("client.layouts.app")
@section("title", __('client.hosting.ftp.title'))
@section("content")

<style>
    .ft-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .ft-back:hover{color:var(--primary)}
    .ft-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .ft-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(245,158,11,.6)}
    .ft-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .ft-head .sub{font-size:13px;color:var(--muted)}
    .ft-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .ft-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .ft-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .ft-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .ft-fld{flex:1;min-width:140px}
    .ft-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .ft-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px}
    .ft-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .ft-btn:hover{background:var(--primary-dark)}
    .ft-note{padding:16px 18px;display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--muted)}
    .ft-note i{font-size:18px;color:#f59e0b}
    .ft-table{width:100%;border-collapse:collapse}
    .ft-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .ft-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text)}
    .ft-table tbody tr:last-child td{border-bottom:none}
    .ft-table tbody tr:hover{background:var(--primary-light)}
    .ft-user{display:inline-flex;align-items:center;gap:9px;font-weight:600}
    .ft-user .ic{width:30px;height:30px;border-radius:8px;background:rgba(245,158,11,.14);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:15px}
    .ft-home{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:2px 8px;border-radius:6px}
    .ft-badge{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:999px}
    .ft-badge.on{background:rgba(16,185,129,.12);color:#059669}
    .ft-act{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--text);list-style:none}
    .ft-act:hover{border-color:var(--primary);color:var(--primary)}
    .ft-act.danger{width:32px;height:32px;justify-content:center;padding:0;color:var(--muted)}
    .ft-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .ft-pop{position:absolute;right:0;z-index:20;margin-top:8px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;box-shadow:var(--shadow-md);min-width:250px;text-align:left}
    .ft-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="ft-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="ft-head">
    <div class="ft-head-ic"><i class="ri-folder-transfer-line"></i></div>
    <div><h1>{{ __('client.hosting.ftp.title') }}</h1><div class="sub">{{ __('client.hosting.ftp.subtitle') }}</div></div>
    <span class="ft-cnt">{{ $policy['max'] < 0 ? $policy['used'].' / ∞' : $policy['used'].' / '.$policy['max'] }}</span>
</div>

@if(!empty($ftpHost))
<div class="ft-card">
    <div class="ft-ch"><i class="ri-plug-line"></i>{{ __('client.hosting.ftp.connection') }}</div>
    <div style="padding:14px 18px;display:flex;gap:28px;flex-wrap:wrap">
        <div><div class="ft-lbl">{{ __('client.hosting.ftp.host') }}</div><span class="ft-home" style="font-size:12.5px">{{ $ftpHost }}</span></div>
        <div><div class="ft-lbl">{{ __('client.hosting.ftp.port') }}</div><span class="ft-home" style="font-size:12.5px">21</span></div>
        <div><div class="ft-lbl">{{ __('client.hosting.ftp.protocol') }}</div><span class="ft-home" style="font-size:12.5px">FTP / FTPS (TLS)</span></div>
    </div>
    <div class="ft-note" style="border-top:1px solid var(--border)"><i class="ri-information-line"></i>{{ __('client.hosting.ftp.protocol_hint') }}</div>
</div>
@endif

{{-- Create (only when the plan allows it) --}}
<div class="ft-card">
    <div class="ft-ch"><i class="ri-user-add-line"></i>{{ __('client.hosting.ftp.create_title') }}</div>
    @if(! $policy['enabled'])
        <div class="ft-note"><i class="ri-lock-line"></i>{{ __('client.hosting.ftp.plan_disabled') }}</div>
    @elseif(! $policy['can_create'])
        <div class="ft-note"><i class="ri-error-warning-line"></i>{{ __('client.hosting.ftp.limit_reached') }} ({{ $policy['used'] }}/{{ $policy['max'] }})</div>
    @else
    <form method="POST" action="{{ route('client.services.ftp.store', $service) }}" class="ft-form">
        @csrf
        <div class="ft-fld"><label class="ft-lbl">{{ __('client.hosting.ftp.username') }}</label><input type="text" name="username" required maxlength="32" pattern="[A-Za-z0-9._-]+" class="ft-inp" placeholder="ftpuser"></div>
        <div class="ft-fld"><label class="ft-lbl">{{ __('client.hosting.ftp.directory') }}</label>
            <select name="domain_id" class="ft-inp"><option value="">{{ __('client.hosting.ftp.home_default') }}</option>@foreach($domains as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
        <div class="ft-fld"><label class="ft-lbl">{{ __('client.hosting.ftp.password') }}</label><input type="password" name="password" required minlength="8" maxlength="128" class="ft-inp" autocomplete="new-password"></div>
        <div style="width:120px"><label class="ft-lbl">{{ __('client.hosting.ftp.quota_mb') }}</label><input type="number" name="quota_mb" min="0" max="1048576" class="ft-inp" placeholder="0"></div>
        <button type="submit" class="ft-btn"><i class="ri-add-line"></i>{{ __('client.hosting.ftp.create') }}</button>
    </form>
    @endif
</div>

<div class="ft-card">
    <div class="ft-ch"><i class="ri-list-check-2"></i>{{ __('client.hosting.ftp.accounts') }}</div>
    @if(empty($accounts))
    <div class="ft-empty">{{ __('client.hosting.ftp.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="ft-table">
        <thead><tr><th>{{ __('client.hosting.ftp.username') }}</th><th>{{ __('client.hosting.ftp.home') }}</th><th>{{ __('client.hosting.ftp.usage') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
            @foreach($accounts as $f)
            <tr>
                <td><span class="ft-user"><span class="ic"><i class="ri-user-line"></i></span>{{ $f['username'] }}</span></td>
                <td><span class="ft-home">{{ $f['home'] ?: '—' }}</span></td>
                <td class="text-muted" style="font-size:12.5px;color:var(--muted)">{{ number_format($f['used_mb']) }} @if($f['quota_mb'] > 0)/ {{ number_format($f['quota_mb']) }} MB @else MB @endif</td>
                <td><span class="ft-badge on"><span style="width:6px;height:6px;border-radius:50%;background:currentColor"></span>{{ $f['status'] ?: 'active' }}</span></td>
                <td style="text-align:right;white-space:nowrap">
                    <details style="display:inline-block;position:relative">
                        <summary class="ft-act"><i class="ri-key-2-line"></i>{{ __('client.hosting.ftp.change_password') }}</summary>
                        <div class="ft-pop"><form method="POST" action="{{ route('client.services.ftp.password', $service) }}">
                            @csrf<input type="hidden" name="ftp_id" value="{{ $f['id'] }}">
                            <label class="ft-lbl">{{ __('client.hosting.ftp.new_password') }}</label>
                            <input type="password" name="password" required minlength="8" maxlength="128" class="ft-inp" style="margin-bottom:8px" autocomplete="new-password">
                            <button type="submit" class="ft-btn" style="width:100%;justify-content:center">{{ __('client.hosting.ftp.save') }}</button>
                        </form></div>
                    </details>
                    <form method="POST" action="{{ route('client.services.ftp.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.ftp.delete_confirm') }}')">
                        @csrf<input type="hidden" name="ftp_id" value="{{ $f['id'] }}">
                        <button type="submit" class="ft-act danger" title="{{ __('client.hosting.ftp.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

<script>
(function(){
    document.querySelectorAll('.ft-card details').forEach(function(d){
        var pop=d.querySelector('.ft-pop'),sum=d.querySelector('summary'); if(!pop||!sum)return;
        d.addEventListener('toggle',function(){
            if(!d.open){pop.style.position='';pop.style.top='';pop.style.right='';pop.style.marginTop='';return;}
            document.querySelectorAll('.ft-card details[open]').forEach(function(o){if(o!==d)o.removeAttribute('open');});
            var r=sum.getBoundingClientRect();pop.style.position='fixed';pop.style.top=(r.bottom+6)+'px';pop.style.right=Math.max(12,(window.innerWidth-r.right))+'px';pop.style.marginTop='0';
        });
    });
    document.addEventListener('click',function(e){document.querySelectorAll('.ft-card details[open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open');});});
})();
</script>

@endsection
