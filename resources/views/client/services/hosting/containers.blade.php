@extends("client.layouts.app")
@section("title", __('client.hosting.containers.title'))
@section("content")

<style>
    .ct-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .ct-back:hover{color:var(--primary)}
    .ct-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .ct-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(14,165,233,.6)}
    .ct-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .ct-head .sub{font-size:13px;color:var(--muted)}
    .ct-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .ct-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .ct-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .ct-note{padding:16px 18px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--muted);line-height:1.5}
    .ct-note i{font-size:18px;color:#f59e0b;flex-shrink:0}
    .ct-note.info i{color:var(--primary)}
    .ct-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}

    /* App catalogue */
    .ct-apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;padding:18px}
    .ct-app{border:1px solid var(--border);border-radius:12px;padding:14px;text-align:center;cursor:pointer;background:var(--bg);transition:transform .14s,border-color .14s}
    .ct-app:hover{transform:translateY(-3px);border-color:var(--primary)}
    .ct-app.on{border-color:var(--primary);background:var(--primary-light)}
    .ct-mark{width:42px;height:42px;margin:0 auto 9px;border-radius:11px;border:1px solid;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;letter-spacing:-.5px}
    .ct-app .nm{font-size:12.5px;font-weight:700;color:var(--text);line-height:1.25}
    .ct-app .ds{font-size:10.5px;color:var(--muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ct-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:0 18px 18px}
    .ct-fld{flex:1;min-width:150px}
    .ct-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .ct-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px;box-sizing:border-box}
    .ct-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .ct-btn:hover{background:var(--primary-dark)}
    .ct-btn:disabled{opacity:.5;cursor:not-allowed}

    /* Running list */
    .ct-table{width:100%;border-collapse:collapse}
    .ct-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .ct-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)}
    .ct-table tbody tr:last-child td{border-bottom:none}
    .ct-name{font-weight:700}
    .ct-img{font-size:11.5px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .ct-run{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669}
    .ct-stop{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted)}
    .ct-meter{font-size:11.5px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .ct-bar{height:5px;border-radius:999px;background:var(--border);overflow:hidden;margin-top:4px;width:90px}
    .ct-bar span{display:block;height:100%;background:var(--primary)}
    .ct-acts{display:inline-flex;gap:6px;justify-content:flex-end}
    .ct-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);color:var(--muted);cursor:pointer;background:transparent}
    .ct-act:hover{background:var(--primary-light);color:var(--primary);border-color:var(--primary)}
    .ct-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .ct-port{font-size:11px;font-family:ui-monospace,Menlo,monospace;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:1px 7px;border-radius:6px;margin-right:4px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="ct-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="ct-head">
    <div class="ct-head-ic"><i class="ri-apps-2-line"></i></div>
    <div><h1>{{ __('client.hosting.containers.title') }}</h1><div class="sub">{{ __('client.hosting.containers.subtitle') }}</div></div>
    <span class="ct-cnt">{{ $policy['max'] < 0 ? $policy['used'].' / ∞' : $policy['used'].' / '.$policy['max'] }}</span>
</div>

@if(! $policy['enabled'])
<div class="ct-card"><div class="ct-note"><i class="ri-lock-line"></i>{{ __('client.hosting.containers.plan_disabled') }}</div></div>
@else

@if($policy['can_create'])
<div class="ct-card">
    <div class="ct-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.containers.install_title') }}</div>
    @if(empty($templates))
        <div class="ct-empty">{{ __('client.hosting.containers.no_apps') }}</div>
    @else
    <form method="POST" action="{{ route('client.services.containers.store', $service) }}">
        @csrf
        <input type="hidden" name="slug" id="ct-slug" value="">
        <div class="ct-apps">
            @foreach($templates as $t)
            @php
                // One mark per app, drawn here rather than fetched.
                //
                // The catalogue's logo_url points at other people's servers:
                // most apps have none, and of the ones that do, roughly half
                // are dead links that render as a broken image. That made the
                // grid look half-finished and sent every customer's browser to
                // github/jsdelivr on page load. A letter tile keyed off the
                // slug is the same for every app, always loads, and leaks
                // nothing.
                $ctHue = crc32($t['slug']) % 360;
                $ctInitial = mb_strtoupper(mb_substr(trim($t['name']) ?: $t['slug'], 0, 1));
            @endphp
            <div class="ct-app" data-slug="{{ $t['slug'] }}" onclick="ctPick(this)" title="{{ $t['description'] }}">
                <div class="ct-mark" style="background:hsl({{ $ctHue }},62%,94%);color:hsl({{ $ctHue }},52%,34%);border-color:hsl({{ $ctHue }},45%,84%)">{{ $ctInitial }}</div>
                <div class="nm">{{ $t['name'] }}</div>
                <div class="ds">{{ $t['description'] }}</div>
            </div>
            @endforeach
        </div>
        <div class="ct-form">
            <div class="ct-fld" style="max-width:260px">
                <label class="ct-lbl">{{ __('client.hosting.containers.name') }}</label>
                <input type="text" name="name" maxlength="40" pattern="[a-zA-Z0-9-]*" class="ct-inp" placeholder="{{ __('client.hosting.containers.name_ph') }}">
            </div>
            <button type="submit" class="ct-btn" id="ct-submit" disabled><i class="ri-download-2-line"></i>{{ __('client.hosting.containers.install') }}</button>
        </div>
    </form>
    <div class="ct-note info"><i class="ri-information-line"></i>{{ __('client.hosting.containers.install_hint') }}</div>
    @endif
</div>
@else
<div class="ct-card"><div class="ct-note"><i class="ri-error-warning-line"></i>{{ __('client.hosting.containers.limit_reached') }} ({{ $policy['used'] }}/{{ $policy['max'] }})</div></div>
@endif

<div class="ct-card">
    <div class="ct-ch"><i class="ri-apps-2-line"></i>{{ __('client.hosting.containers.running_title') }}</div>
    @if(empty($containers))
    <div class="ct-empty">{{ __('client.hosting.containers.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="ct-table">
        <thead><tr>
            <th>{{ __('client.hosting.containers.app') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th>{{ __('client.hosting.containers.resources') }}</th>
            <th>{{ __('client.hosting.containers.ports') }}</th>
            <th style="text-align:right">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
            @foreach($containers as $c)
            <tr>
                <td><div class="ct-name">{{ $c['name'] }}</div><div class="ct-img">{{ $c['image'] }}</div></td>
                <td>
                    @if($c['state'] === 'running')<span class="ct-run">{{ __('client.hosting.containers.running') }}</span>
                    @else<span class="ct-stop">{{ $c['state'] ?: __('client.hosting.containers.stopped') }}</span>@endif
                </td>
                <td>
                    @if($c['state'] === 'running')
                        <div class="ct-meter">CPU {{ number_format($c['cpu_percent'], 1) }}%</div>
                        @php($memPct = $c['mem_limit'] > 0 ? min(100, $c['mem_usage'] / $c['mem_limit'] * 100) : 0)
                        <div class="ct-meter">RAM {{ $c['mem_usage'] >= 1048576 ? number_format($c['mem_usage']/1048576, 0).' MB' : '—' }}@if($c['mem_limit'] > 0) / {{ number_format($c['mem_limit']/1048576, 0) }} MB @endif</div>
                        @if($c['mem_limit'] > 0)<div class="ct-bar"><span style="width:{{ $memPct }}%"></span></div>@endif
                    @else
                        <span class="ct-meter">—</span>
                    @endif
                </td>
                <td>@forelse($c['ports'] as $p)<span class="ct-port">{{ $p }}</span>@empty<span class="ct-meter">—</span>@endforelse</td>
                <td style="text-align:right">
                    <div class="ct-acts">
                        @if($c['state'] === 'running')
                        <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                            <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="restart">
                            <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.restart') }}"><i class="ri-restart-line"></i></button>
                        </form>
                        <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                            <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="stop">
                            <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.stop') }}"><i class="ri-stop-line"></i></button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('client.services.containers.action', $service) }}">@csrf
                            <input type="hidden" name="container_id" value="{{ $c['id'] }}"><input type="hidden" name="action" value="start">
                            <button type="submit" class="ct-act" title="{{ __('client.hosting.containers.start') }}"><i class="ri-play-line"></i></button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('client.services.containers.destroy', $service) }}" onsubmit="return confirm('{{ __('client.hosting.containers.delete_confirm') }}')">@csrf
                            <input type="hidden" name="container_id" value="{{ $c['id'] }}">
                            <button type="submit" class="ct-act danger" title="{{ __('client.hosting.containers.delete') }}"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div class="ct-note"><i class="ri-terminal-box-line"></i>{{ __('client.hosting.containers.panel_hint') }}</div>
    @endif
</div>
@endif

<script>
// Pick an app before the install button becomes usable — a deploy without a
// chosen template is the one mistake this form can make.
function ctPick(el){
    document.querySelectorAll('.ct-app').forEach(function(a){ a.classList.remove('on'); });
    el.classList.add('on');
    document.getElementById('ct-slug').value = el.getAttribute('data-slug') || '';
    document.getElementById('ct-submit').disabled = false;
}
</script>

@endsection
