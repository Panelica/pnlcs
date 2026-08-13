@extends("client.layouts.app")
@section("title", __('client.hosting.email.title'))
@section("content")

<style>
    .em-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .em-back:hover{color:var(--primary)}
    .em-head{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap}
    .em-head-l{display:flex;align-items:center;gap:14px}
    .em-head-ic{width:46px;height:46px;border-radius:13px;background:rgba(139,92,246,.13);color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:24px}
    .em-head h1{font-size:21px;font-weight:800;margin:0;letter-spacing:-.4px;color:var(--text)}
    .em-head .sub{font-size:13px;color:var(--muted)}
    .em-count{font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 12px;border-radius:999px}
    .em-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px;overflow:hidden}
    .em-card-h{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text)}
    .em-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .em-fld{flex:1;min-width:150px}
    .em-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .em-inp{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px}
    .em-at{font-size:20px;color:var(--muted);padding-bottom:9px}
    .em-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .em-btn:hover{background:var(--primary-dark)}
    .em-table{width:100%;border-collapse:collapse}
    .em-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .em-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text)}
    .em-table tbody tr:last-child td{border-bottom:none}
    .em-table tbody tr:hover{background:var(--primary-light)}
    .em-addr{display:inline-flex;align-items:center;gap:10px;font-weight:600}
    .em-addr .ic{width:30px;height:30px;border-radius:8px;background:rgba(139,92,246,.13);color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:15px}
    .em-badge{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:999px}
    .em-badge.on{background:rgba(16,185,129,.12);color:#059669}
    .em-badge.off{background:rgba(245,158,11,.14);color:#b45309}
    .em-act{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--text);list-style:none;text-decoration:none}
    .em-act:hover{border-color:var(--primary);color:var(--primary)}
    .em-act.danger{width:32px;height:32px;justify-content:center;padding:0;color:var(--muted)}
    .em-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .em-pop{position:absolute;right:0;z-index:20;margin-top:8px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;box-shadow:var(--shadow-md);width:264px;box-sizing:border-box}
    .em-empty{padding:28px;text-align:center;color:var(--muted);font-size:13.5px}
    .em-pop,.em-pop *{box-sizing:border-box}
</style>

<a href="{{ route('client.services.show', $service) }}" class="em-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="em-head">
    <div class="em-head-l">
        <div class="em-head-ic"><i class="ri-mail-line"></i></div>
        <div><h1>{{ __('client.hosting.email.title') }}</h1><div class="sub">{{ __('client.hosting.email.subtitle') }}</div></div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
        @if(!empty($webmailUrl))
        <a href="{{ $webmailUrl }}" target="_blank" rel="noopener" class="em-btn" style="text-decoration:none"><i class="ri-inbox-2-line"></i>{{ __('client.hosting.email.webmail') }}<i class="ri-external-link-line" style="font-size:12px;opacity:.7"></i></a>
        @endif
        <span class="em-count">{{ count($emails) }} {{ __('client.hosting.email.mailboxes') }}</span>
    </div>
</div>

@if(empty($domains))
<div class="em-card"><div class="em-empty">{{ __('client.hosting.email.no_domains') }}</div></div>
@else
<div class="em-card">
    <div class="em-card-h">{{ __('client.hosting.email.create_title') }}</div>
    <form method="POST" action="{{ route('client.services.emails.store', $service) }}" class="em-form">
        @csrf
        <div class="em-fld"><label class="em-lbl">{{ __('client.hosting.email.mailbox_name') }}</label><input type="text" name="local_part" required maxlength="64" class="em-inp" placeholder="info" value="{{ old('local_part') }}"></div>
        <div class="em-at">@</div>
        <div class="em-fld"><label class="em-lbl">{{ __('client.hosting.email.domain') }}</label>
            <select name="domain_id" required class="em-inp">
                @foreach($domains as $id => $name)<option value="{{ $id }}" @selected(old('domain_id') === $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="em-fld"><label class="em-lbl">{{ __('client.hosting.email.password') }}</label><input type="password" name="password" required minlength="8" maxlength="128" class="em-inp" autocomplete="new-password"></div>
        <div style="width:120px"><label class="em-lbl">{{ __('client.hosting.email.quota_mb') }}</label><input type="number" name="quota_mb" min="0" max="1048576" class="em-inp" placeholder="1024" value="{{ old('quota_mb') }}"></div>
        <button type="submit" class="em-btn"><i class="ri-add-line"></i>{{ __('client.hosting.email.create_button') }}</button>
    </form>
    @error('local_part')<div style="color:#dc2626;font-size:12px;padding:0 18px 12px">{{ $message }}</div>@enderror
    @error('password')<div style="color:#dc2626;font-size:12px;padding:0 18px 12px">{{ $message }}</div>@enderror
</div>
@endif

<div class="em-card">
    <div class="em-card-h">{{ __('client.hosting.email.accounts_title') }}</div>
    @if(empty($emails))
    <div class="em-empty">{{ __('client.hosting.email.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="em-table">
        <thead><tr><th>{{ __('client.hosting.email.address') }}</th><th>{{ __('client.hosting.email.usage') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
            @foreach($emails as $mail)
            @php($on = strtolower($mail['status']) === 'active')
            <tr>
                <td><span class="em-addr"><span class="ic"><i class="ri-at-line"></i></span>{{ $mail['email'] }}</span></td>
                <td class="em-meta" style="color:var(--muted);font-size:12.5px">{{ number_format($mail['used_mb']) }} @if($mail['quota_mb'] > 0)/ {{ number_format($mail['quota_mb']) }} MB @else/ {{ __('client.hosting.email.unlimited') }} @endif</td>
                <td><span class="em-badge {{ $on ? 'on' : 'off' }}"><span style="width:6px;height:6px;border-radius:50%;background:currentColor"></span>{{ $mail['status'] ?: '-' }}</span></td>
                <td style="text-align:right;white-space:nowrap">
                    <details style="display:inline-block;position:relative;text-align:left">
                        <summary class="em-act"><i class="ri-key-2-line"></i>{{ __('client.hosting.email.change_password') }}</summary>
                        <div class="em-pop">
                            <form method="POST" action="{{ route('client.services.emails.password', $service) }}">
                                @csrf<input type="hidden" name="email_id" value="{{ $mail['id'] }}">
                                <label class="em-lbl">{{ __('client.hosting.email.new_password') }}</label>
                                <input type="password" name="password" required minlength="8" maxlength="128" class="em-inp" autocomplete="new-password" style="margin-bottom:9px">
                                <button type="submit" class="em-btn" style="width:100%;justify-content:center">{{ __('client.hosting.email.save') }}</button>
                            </form>
                        </div>
                    </details>
                    <form method="POST" action="{{ route('client.services.emails.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.email.delete_confirm') }}')">
                        @csrf<input type="hidden" name="email_id" value="{{ $mail['id'] }}">
                        <button type="submit" class="em-act danger" title="{{ __('client.hosting.email.delete') }}"><i class="ri-delete-bin-line"></i></button>
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
    document.querySelectorAll('.em-card details').forEach(function(d){
        var pop=d.querySelector('.em-pop'),sum=d.querySelector('summary'); if(!pop||!sum)return;
        d.addEventListener('toggle',function(){
            if(!d.open){pop.style.position='';pop.style.top='';pop.style.left='';pop.style.right='';pop.style.marginTop='';return;}
            document.querySelectorAll('.em-card details[open]').forEach(function(o){if(o!==d)o.removeAttribute('open');});
            var r=sum.getBoundingClientRect();var L=Math.min(r.right-264,window.innerWidth-264-12);if(L<12)L=12;
            pop.style.position='fixed';pop.style.top=(r.bottom+6)+'px';pop.style.left=L+'px';pop.style.right='auto';pop.style.marginTop='0';
        });
    });
    document.addEventListener('click',function(e){document.querySelectorAll('.em-card details[open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open');});});
})();
</script>

@endsection
