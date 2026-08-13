@extends("client.layouts.app")
@section("title", __('client.hosting.databases.title'))
@section("content")

@php
    $domains = collect($groups)->mapWithKeys(fn ($g) => [$g['domain_id'] => $g['domain']]);
    $totalDbs = collect($groups)->flatMap(fn ($g) => collect($g['users'])->pluck('database_name'))->filter()->unique()->count();
@endphp

<style>
    .db-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .db-back:hover{color:var(--primary)}
    .db-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .db-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:25px;box-shadow:0 8px 18px -6px rgba(14,165,233,.6)}
    .db-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .db-head .sub{font-size:13px;color:var(--muted)}
    .db-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .db-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .db-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .db-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .db-fld{flex:1;min-width:140px}
    .db-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .db-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px}
    .db-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .db-btn:hover{background:var(--primary-dark)}
    .db-grp{padding:16px 18px;border-bottom:1px solid var(--border)}
    .db-grp:last-child{border-bottom:none}
    .db-dom{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px}
    .db-dom i{color:#0ea5e9}
    .db-name{display:inline-flex;align-items:center;gap:9px;font-family:ui-monospace,Menlo,monospace;font-size:13px;font-weight:700;color:var(--text)}
    .db-name .ic{width:28px;height:28px;border-radius:8px;background:rgba(14,165,233,.13);color:#0ea5e9;display:flex;align-items:center;justify-content:center;font-size:15px}
    .db-utable{width:100%;border-collapse:collapse;margin-top:8px}
    .db-utable td{padding:8px 10px;border-top:1px solid var(--border);font-size:13px;color:var(--text)}
    .db-role{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(14,165,233,.12);color:#0369a1}
    .db-prim{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;background:rgba(16,185,129,.14);color:#059669;padding:2px 7px;border-radius:999px;margin-left:6px}
    .db-act{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--text);list-style:none;text-decoration:none}
    .db-act:hover{border-color:var(--primary);color:var(--primary)}
    .db-act.danger{color:var(--muted)}
    .db-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .db-pop{position:absolute;right:0;z-index:20;margin-top:6px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;box-shadow:var(--shadow-md);width:264px;box-sizing:border-box;text-align:left}
    .db-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
    .db-pop,.db-pop *{box-sizing:border-box}
    .db-pop form{display:flex;flex-direction:column;align-items:stretch}
    .db-pop .db-btn{width:100%;justify-content:center}
    .db-pop .db-inp{width:100%}
</style>

<a href="{{ route('client.services.show', $service) }}" class="db-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="db-head">
    <div class="db-head-ic"><i class="ri-database-2-line"></i></div>
    <div><h1>{{ __('client.hosting.databases.title') }}</h1><div class="sub">{{ __('client.hosting.databases.subtitle') }}</div></div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
        @if(!empty($phpMyAdminUrl))
        <a href="{{ $phpMyAdminUrl }}" target="_blank" rel="noopener" class="db-btn" style="text-decoration:none;background:#0ea5e9"><i class="ri-table-line"></i>{{ __('client.hosting.databases.phpmyadmin') }}<i class="ri-external-link-line" style="font-size:12px;opacity:.7"></i></a>
        @endif
        <span class="db-cnt">{{ $totalDbs }} {{ __('client.hosting.databases.title') }}</span>
    </div>
</div>

@if($domains->isEmpty())
<div class="db-card"><div class="db-empty">{{ __('client.hosting.databases.no_domains') }}</div></div>
@else
{{-- Create database --}}
<div class="db-card">
    <div class="db-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.databases.create_title') }}</div>
    <form method="POST" action="{{ route('client.services.databases.store', $service) }}" class="db-form">
        @csrf
        <div class="db-fld"><label class="db-lbl">{{ __('client.hosting.databases.domain') }}</label>
            <select name="domain_id" required class="db-inp">@foreach($domains as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
        <div class="db-fld"><label class="db-lbl">{{ __('client.hosting.databases.db_name') }}</label><input type="text" name="database_name" required maxlength="32" pattern="[A-Za-z0-9_]+" class="db-inp" placeholder="shop"></div>
        <div class="db-fld"><label class="db-lbl">{{ __('client.hosting.databases.db_user') }}</label><input type="text" name="database_user" required maxlength="32" pattern="[A-Za-z0-9_]+" class="db-inp" placeholder="shopuser"></div>
        <div class="db-fld"><label class="db-lbl">{{ __('client.hosting.databases.password') }}</label><input type="password" name="password" required minlength="8" maxlength="128" class="db-inp" autocomplete="new-password"></div>
        <button type="submit" class="db-btn"><i class="ri-add-line"></i>{{ __('client.hosting.databases.create') }}</button>
    </form>
</div>

{{-- Databases per domain --}}
<div class="db-card">
    <div class="db-ch"><i class="ri-stack-line"></i>{{ __('client.hosting.databases.title') }}</div>
    @if($totalDbs === 0)
    <div class="db-empty">{{ __('client.hosting.databases.empty') }}</div>
    @else
    @foreach($groups as $g)
        @php($byDb = collect($g['users'])->groupBy('database_name'))
        @foreach($byDb as $dbName => $users)
            @continue($dbName === '')
        <div class="db-grp">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <span class="db-name"><span class="ic"><i class="ri-database-2-line"></i></span>{{ $dbName }}</span>
                <div style="display:flex;gap:8px">
                    <details style="position:relative">
                        <summary class="db-act"><i class="ri-user-add-line"></i>{{ __('client.hosting.databases.add_user') }}</summary>
                        <div class="db-pop"><form method="POST" action="{{ route('client.services.databases.users.store', $service) }}">
                            @csrf<input type="hidden" name="domain_id" value="{{ $g['domain_id'] }}">
                            <label class="db-lbl">{{ __('client.hosting.databases.new_user') }}</label>
                            <input type="text" name="username" required maxlength="32" pattern="[A-Za-z0-9_]+" class="db-inp" style="margin-bottom:8px">
                            <label class="db-lbl">{{ __('client.hosting.databases.password') }}</label>
                            <input type="password" name="password" required minlength="8" class="db-inp" style="margin-bottom:8px" autocomplete="new-password">
                            <label class="db-lbl">{{ __('client.hosting.databases.role') }}</label>
                            <select name="role" class="db-inp" style="margin-bottom:8px"><option value="readWrite">readWrite</option><option value="read">read</option><option value="dbAdmin">dbAdmin</option><option value="dbOwner">dbOwner</option></select>
                            <button type="submit" class="db-btn" style="width:100%;justify-content:center">{{ __('client.hosting.databases.add_user') }}</button>
                        </form></div>
                    </details>
                    <form method="POST" action="{{ route('client.services.databases.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.databases.delete_db_confirm') }}')">
                        @csrf<input type="hidden" name="domain_id" value="{{ $g['domain_id'] }}"><input type="hidden" name="database_name" value="{{ $dbName }}">
                        <button type="submit" class="db-act danger" title="{{ __('client.hosting.databases.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </div>
            </div>
            <table class="db-utable"><tbody>
                @foreach($users as $u)
                <tr>
                    <td style="font-family:ui-monospace,Menlo,monospace">{{ $u['username'] }}@if($u['is_primary'])<span class="db-prim">{{ __('client.hosting.databases.primary') }}</span>@endif</td>
                    <td><span class="db-role">{{ $u['role'] }}</span></td>
                    <td style="text-align:right;white-space:nowrap">
                        <details style="display:inline-block;position:relative">
                            <summary class="db-act"><i class="ri-key-2-line"></i>{{ __('client.hosting.databases.change_password') }}</summary>
                            <div class="db-pop"><form method="POST" action="{{ route('client.services.databases.users.password', $service) }}">
                                @csrf<input type="hidden" name="user_id" value="{{ $u['id'] }}">
                                <label class="db-lbl">{{ __('client.hosting.databases.new_password') }}</label>
                                <input type="password" name="password" required minlength="8" class="db-inp" style="margin-bottom:8px" autocomplete="new-password">
                                <button type="submit" class="db-btn" style="width:100%;justify-content:center">{{ __('client.hosting.databases.save') }}</button>
                            </form></div>
                        </details>
                        @unless($u['is_primary'])
                        <form method="POST" action="{{ route('client.services.databases.users.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.databases.delete_user_confirm') }}')">
                            @csrf<input type="hidden" name="user_id" value="{{ $u['id'] }}">
                            <button type="submit" class="db-act danger" title="{{ __('client.hosting.databases.delete') }}"><i class="ri-delete-bin-line"></i></button>
                        </form>
                        @endunless
                    </td>
                </tr>
                @endforeach
            </tbody></table>
        </div>
        @endforeach
    @endforeach
    @endif
</div>
@endif

<script>
// Anchor each dropdown to the viewport when it opens, so it is never clipped by
// a card boundary or a scroll container regardless of where it sits in the table.
(function(){
    document.querySelectorAll('.db-card details').forEach(function(d){
        var pop = d.querySelector('.db-pop');
        var sum = d.querySelector('summary');
        if(!pop || !sum) return;
        d.addEventListener('toggle', function(){
            if(!d.open){ pop.style.position=''; pop.style.top=''; pop.style.right=''; pop.style.marginTop=''; return; }
            document.querySelectorAll('.db-card details[open]').forEach(function(o){ if(o!==d) o.removeAttribute('open'); });
            var r = sum.getBoundingClientRect();
            pop.style.position='fixed';
            pop.style.top=(r.bottom+6)+'px';
            pop.style.right=Math.max(12,(window.innerWidth - r.right))+'px';
            pop.style.marginTop='0';
        });
    });
    document.addEventListener('click', function(e){
        document.querySelectorAll('.db-card details[open]').forEach(function(d){ if(!d.contains(e.target)) d.removeAttribute('open'); });
    });
})();
</script>

@endsection
