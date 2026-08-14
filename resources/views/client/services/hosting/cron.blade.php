@extends("client.layouts.app")
@section("title", __('client.hosting.cron.title'))
@section("content")

<style>
    .cr-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .cr-back:hover{color:var(--primary)}
    .cr-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .cr-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#ec4899,#db2777);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(236,72,153,.6)}
    .cr-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .cr-head .sub{font-size:13px;color:var(--muted)}
    .cr-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .cr-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .cr-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .cr-form{padding:18px}
    .cr-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:13px}
    .cr-fld{flex:1;min-width:150px}
    .cr-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .cr-inp,.cr-sel,.cr-ta{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px;box-sizing:border-box}
    .cr-ta{font-family:ui-monospace,Menlo,monospace;resize:vertical;min-height:44px}
    .cr-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .cr-btn:hover{background:var(--primary-dark)}
    .cr-seg{display:inline-flex;border:1px solid var(--border);border-radius:9px;overflow:hidden}
    .cr-seg label{padding:8px 14px;font-size:12.5px;font-weight:600;color:var(--muted);cursor:pointer;background:var(--bg)}
    .cr-seg input{display:none}
    .cr-seg input:checked+span{color:#fff}
    .cr-seg label:has(input:checked){background:var(--primary)}
    .cr-adv{display:flex;gap:8px;flex-wrap:wrap}
    .cr-adv .cr-fld{min-width:90px}
    .cr-note{padding:16px 18px;display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--muted)}
    .cr-note i{font-size:18px;color:#f59e0b}
    .cr-hint{font-size:11.5px;color:var(--muted);margin-top:4px}
    .cr-check{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text)}
    .cr-table{width:100%;border-collapse:collapse}
    .cr-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .cr-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text);vertical-align:top}
    .cr-table tbody tr:last-child td{border-bottom:none}
    .cr-name{font-weight:700}
    .cr-dom{font-size:11.5px;color:var(--muted)}
    .cr-sched{font-family:ui-monospace,Menlo,monospace;font-size:12px;background:var(--bg);border:1px solid var(--border);padding:2px 7px;border-radius:6px;display:inline-block}
    .cr-cmd{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;color:var(--muted);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .cr-on{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669}
    .cr-off{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted)}
    .cr-acts{display:flex;gap:6px;justify-content:flex-end}
    .cr-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);color:var(--muted);cursor:pointer;background:transparent}
    .cr-act:hover{background:var(--primary-light);color:var(--primary);border-color:var(--primary)}
    .cr-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .cr-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
    .cr-out{margin:0 18px 18px;border:1px solid var(--border);border-radius:10px;background:#0b1020;color:#d1e0ff;padding:12px 14px;font-family:ui-monospace,Menlo,monospace;font-size:12px;white-space:pre-wrap;max-height:240px;overflow:auto}
</style>

<a href="{{ route('client.services.show', $service) }}" class="cr-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="cr-head">
    <div class="cr-head-ic"><i class="ri-time-line"></i></div>
    <div><h1>{{ __('client.hosting.cron.title') }}</h1><div class="sub">{{ __('client.hosting.cron.subtitle') }}</div></div>
    <span class="cr-cnt">{{ $policy['max'] < 0 ? $policy['used'].' / ∞' : $policy['used'].' / '.$policy['max'] }}</span>
</div>

@if(session('cron_output') !== null)
<div class="cr-card"><div class="cr-ch"><i class="ri-terminal-box-line"></i>{{ __('client.hosting.cron.output') }}</div>
    <div class="cr-out">{{ session('cron_output') === '' ? __('client.hosting.cron.no_output') : session('cron_output') }}</div>
</div>
@endif

@if(empty($domains))
<div class="cr-card"><div class="cr-empty">{{ __('client.hosting.cron.no_domains') }}</div></div>
@elseif(! $policy['enabled'])
<div class="cr-card"><div class="cr-note"><i class="ri-lock-line"></i>{{ __('client.hosting.cron.plan_disabled') }}</div></div>
@else
<div class="cr-card">
    <div class="cr-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.cron.create_title') }}</div>
    @if(! $policy['can_create'])
        <div class="cr-note"><i class="ri-error-warning-line"></i>{{ __('client.hosting.cron.limit_reached') }} ({{ $policy['used'] }}/{{ $policy['max'] }})</div>
    @else
    <form method="POST" action="{{ route('client.services.cron.store', $service) }}" class="cr-form">
        @csrf
        <div class="cr-row">
            <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.task_name') }}</label><input type="text" name="task_name" required maxlength="255" class="cr-inp" placeholder="{{ __('client.hosting.cron.task_name_ph') }}"></div>
            <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.domain') }}</label>
                <select name="domain_id" required class="cr-sel">@foreach($domains as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            </div>
        </div>
        <div class="cr-row">
            <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.command') }}</label>
                <textarea name="command" required maxlength="4096" class="cr-ta" rows="2" placeholder="{{ __('client.hosting.cron.command_ph') }}"></textarea>
                <div class="cr-hint">{{ __('client.hosting.cron.command_hint') }}</div>
            </div>
        </div>
        <div class="cr-row" style="align-items:flex-end">
            <div class="cr-fld" style="max-width:220px">
                <label class="cr-lbl">{{ __('client.hosting.cron.schedule') }}</label>
                <div class="cr-seg">
                    <label><input type="radio" name="schedule_type" value="basic" checked onchange="crMode(this)"><span>{{ __('client.hosting.cron.basic') }}</span></label>
                    <label><input type="radio" name="schedule_type" value="advanced" onchange="crMode(this)"><span>{{ __('client.hosting.cron.advanced') }}</span></label>
                </div>
            </div>
            <div class="cr-fld" id="cr-basic">
                <select name="preset" class="cr-sel">
                    <option value="everyMinute">{{ __('client.hosting.cron.p.everyMinute') }}</option>
                    <option value="every5Minutes">{{ __('client.hosting.cron.p.every5') }}</option>
                    <option value="every15Minutes">{{ __('client.hosting.cron.p.every15') }}</option>
                    <option value="every30Minutes">{{ __('client.hosting.cron.p.every30') }}</option>
                    <option value="hourly">{{ __('client.hosting.cron.p.hourly') }}</option>
                    <option value="daily" selected>{{ __('client.hosting.cron.p.daily') }}</option>
                    <option value="weekly">{{ __('client.hosting.cron.p.weekly') }}</option>
                    <option value="monthly">{{ __('client.hosting.cron.p.monthly') }}</option>
                </select>
            </div>
            <div class="cr-fld cr-adv" id="cr-adv" style="display:none;flex:2">
                <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.min') }}</label><input type="text" name="minute" value="*" class="cr-inp"></div>
                <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.hr') }}</label><input type="text" name="hour" value="*" class="cr-inp"></div>
                <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.dom') }}</label><input type="text" name="day_of_month" value="*" class="cr-inp"></div>
                <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.mon') }}</label><input type="text" name="month" value="*" class="cr-inp"></div>
                <div class="cr-fld"><label class="cr-lbl">{{ __('client.hosting.cron.dow') }}</label><input type="text" name="day_of_week" value="*" class="cr-inp"></div>
            </div>
        </div>
        <div class="cr-row" style="align-items:center">
            <label class="cr-check"><input type="checkbox" name="email_on_error" value="1"> {{ __('client.hosting.cron.email_on_error') }}</label>
            <div class="cr-fld" style="max-width:260px"><input type="email" name="email_recipient" class="cr-inp" placeholder="{{ __('client.hosting.cron.email_ph') }}"></div>
            <button type="submit" class="cr-btn" style="margin-left:auto"><i class="ri-add-line"></i>{{ __('client.hosting.cron.create') }}</button>
        </div>
    </form>
    @endif
</div>

<div class="cr-card">
    <div class="cr-ch"><i class="ri-time-line"></i>{{ __('client.hosting.cron.title') }}</div>
    @if(empty($cronJobs))
    <div class="cr-empty">{{ __('client.hosting.cron.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="cr-table">
        <thead><tr>
            <th>{{ __('client.hosting.cron.task') }}</th>
            <th>{{ __('client.hosting.cron.schedule') }}</th>
            <th>{{ __('client.hosting.cron.command') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th style="text-align:right">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
            @foreach($cronJobs as $j)
            <tr>
                <td><div class="cr-name">{{ $j['task_name'] }}</div><div class="cr-dom">{{ $j['domain'] }}</div></td>
                <td><span class="cr-sched">{{ $j['schedule'] }}</span></td>
                <td><div class="cr-cmd" title="{{ $j['command'] }}">{{ $j['command'] }}</div></td>
                <td>@if($j['enabled'])<span class="cr-on">{{ __('client.hosting.cron.enabled') }}</span>@else<span class="cr-off">{{ __('client.hosting.cron.disabled') }}</span>@endif</td>
                <td>
                    <div class="cr-acts">
                        <form method="POST" action="{{ route('client.services.cron.run', $service) }}">@csrf<input type="hidden" name="cron_id" value="{{ $j['id'] }}"><button type="submit" class="cr-act" title="{{ __('client.hosting.cron.run_now') }}"><i class="ri-play-line"></i></button></form>
                        <form method="POST" action="{{ route('client.services.cron.toggle', $service) }}">@csrf<input type="hidden" name="cron_id" value="{{ $j['id'] }}"><button type="submit" class="cr-act" title="{{ $j['enabled'] ? __('client.hosting.cron.disable') : __('client.hosting.cron.enable') }}"><i class="{{ $j['enabled'] ? 'ri-pause-line' : 'ri-play-circle-line' }}"></i></button></form>
                        <form method="POST" action="{{ route('client.services.cron.destroy', $service) }}" onsubmit="return confirm('{{ __('client.hosting.cron.delete_confirm') }}')">@csrf<input type="hidden" name="cron_id" value="{{ $j['id'] }}"><button type="submit" class="cr-act danger" title="{{ __('client.hosting.cron.delete') }}"><i class="ri-delete-bin-line"></i></button></form>
                    </div>
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
function crMode(el){
    var adv = el.value === 'advanced';
    document.getElementById('cr-basic').style.display = adv ? 'none' : '';
    document.getElementById('cr-adv').style.display = adv ? 'flex' : 'none';
}
</script>

@endsection
