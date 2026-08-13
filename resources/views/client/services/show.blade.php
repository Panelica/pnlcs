@extends("client.layouts.app")
@section("title", $service->product?->name ?? __("client.services.title"))
@section("content")

<a href="{{ route("client.services.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.services.back_to_services') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ $service->product?->name ?? __('client.services.title') }}</h1>
        @if($service->domain)<p class="pn-page-subtitle">{{ $service->domain }}</p>@endif
    </div>
    <span class="badge badge-{{ strtolower($service->status) }}" style="font-size:13px;padding:5px 14px">{{ __('client.status.' . strtolower($service->status)) }}</span>
</div>

<div class="pn-2col mb-24">
    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.service_details') }}</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">{{ __('client.cart.product') }}</span><span class="val">{{ $service->product?->name ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.cart.billing_cycle') }}</span><span class="val" style="text-transform:capitalize">{{ $service->billing_cycle ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.amount') }}</span><span class="val">{{ money_fmt($service->amount) }} / {{ $service->billing_cycle }}</span></li>
                <li><span class="key">{{ __('client.services.next_due_date') }}</span><span class="val">{{ $service->next_due_date?->format(date_fmt()) ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.registration_date') }}</span><span class="val">{{ $service->registration_date?->format(date_fmt()) ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.checkout.payment_method') }}</span><span class="val" style="text-transform:capitalize">{{ $service->payment_method ?? "N/A" }}</span></li>
                <li>
                    <span class="key">{{ __('client.services.auto_renew') }}</span>
                    <span class="val">
                        <form method="POST" action="{{ route("client.services.autorenew", $service) }}" style="display:inline;">
                            @csrf
                            <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:4px 14px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid {{ $service->auto_renew ? '#22c55e' : '#d1d5db' }};background:{{ $service->auto_renew ? '#f0fdf4' : '#f9fafb' }};color:{{ $service->auto_renew ? '#16a34a' : '#6b7280' }};cursor:pointer;transition:all .15s;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $service->auto_renew ? '#22c55e' : '#d1d5db' }}"></span>
                                {{ $service->auto_renew ? __('client.status.enabled') : __('client.status.disabled') }}
                            </button>
                        </form>
                    </span>
                </li>
            </ul>
        </div>
    </div>

    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.server_info') }}</span></div>
        <div class="pn-card-body">
            <ul class="pn-detail-list">
                <li><span class="key">{{ __('client.services.server') }}</span><span class="val">{{ $service->server->name ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.username') }}</span><span class="val"><span class="pn-code">{{ $service->username ?? "-" }}</span></span></li>
                @if($service->server?->hostname)
                <li><span class="key">{{ __('client.services.hostname') }}</span><span class="val">{{ $service->server->hostname }}</span></li>
                @endif
                @if($service->server?->ip)
                <li><span class="key">{{ __('client.services.ip_address') }}</span><span class="val"><span class="pn-code">{{ $service->server->ip }}</span></span></li>
                @endif
            </ul>
        </div>
    </div>
</div>

@if((($service->server?->type ?? $service->product?->server_type ?? '') === 'panelica') && strtolower($service->status) === 'active')
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.resource_usage') }}</span></div>
    <div class="pn-card-body" id="pn-live-usage"><span class="text-muted">Loading&hellip;</span></div>
</div>
<script>
(function(){
    var el=document.getElementById('pn-live-usage');
    function bar(label,used,limit,unit){
        var pct=limit>0?Math.min(100,Math.round(used/limit*100)):0;
        var col=pct>=90?'var(--danger)':(pct>=75?'var(--warning)':'var(--primary)');
        return '<div style="margin-bottom:16px"><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">'
          +'<span style="font-weight:600">'+label+'</span><span class="text-muted">'+used.toLocaleString()
          +(limit>0?' / '+limit.toLocaleString():'')+' '+unit+(limit>0?' &mdash; <strong>'+pct+'%</strong>':'')+'</span></div>'
          +(limit>0?'<div class="pn-progress-wrap"><div class="pn-progress-fill" style="width:'+pct+'%;background:'+col+'"></div></div>':'')+'</div>';
    }
    function gauge(label,pct,detail){
        pct=Math.min(100,Math.max(0,Math.round(pct)));
        var col=pct>=90?'var(--danger)':(pct>=75?'var(--warning)':'var(--primary)');
        return '<div style="margin-bottom:16px"><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">'
          +'<span style="font-weight:600">'+label+'</span><span class="text-muted">'+detail+' &mdash; <strong>'+pct+'%</strong></span></div>'
          +'<div class="pn-progress-wrap"><div class="pn-progress-fill" style="width:'+pct+'%;background:'+col+'"></div></div></div>';
    }
    function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    fetch("{{ route('client.services.usage', $service) }}",{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();}).then(function(u){
        if(!u||!u.available){el.innerHTML='<span class="text-muted">Live usage is not available right now.</span>';return;}
        var html='';
        if(u.cpu) html+=gauge("{{ __('client.hosting.dashboard.cpu') }}",u.cpu.percent||0,(u.cpu.percent||0).toFixed(2)+'%');
        if(u.ram) html+=gauge("{{ __('client.hosting.dashboard.ram') }}",u.ram.percent||0,(u.ram.used_mb||0).toLocaleString()+' / '+(u.ram.limit_mb||0).toLocaleString()+' MB');
        if(u.disk) html+=bar("{{ __('client.services.disk_usage') }}",u.disk.used_mb||0,u.disk.quota_mb||0,'MB');
        if(u.bandwidth) html+=bar("{{ __('client.services.bandwidth_usage') }}",u.bandwidth.used_mb||0,0,'MB');
        if(u.counts){html+='<div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13px;margin-top:6px">';
          [['domains','Websites'],['databases','Databases'],['emails','Email'],['ftp','FTP']].forEach(function(p){
            html+='<span class="text-muted"><strong>'+(u.counts[p[0]]||0)+'</strong> '+p[1]+'</span>';});
          html+='</div>';}
        if(u.domains){html+='<div style="margin-top:18px"><div style="font-size:13px;font-weight:600;margin-bottom:8px"><i class="ri-global-line" style="margin-right:6px"></i>{{ __('client.hosting.dashboard.domains') }}</div>';
          if(u.domains.length){html+='<div style="display:flex;gap:8px;flex-wrap:wrap">';
            u.domains.forEach(function(d){html+='<span class="pn-code" style="font-size:12px;padding:3px 10px">'+esc(d)+'</span>';});
            html+='</div>';}
          else{html+='<span class="text-muted" style="font-size:13px">{{ __('client.hosting.dashboard.no_domains') }}</span>';}
          html+='</div>';}
        el.innerHTML=html||'<span class="text-muted">No usage data yet.</span>';
      }).catch(function(){el.innerHTML='<span class="text-muted">Live usage is not available right now.</span>';});
})();
</script>
@endif

@if(!empty($hostingFeatures) && strtolower($service->status) === 'active')
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.hosting.title') }}</span></div>
    <div class="pn-card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            @if(in_array('emails', $hostingFeatures, true))
            <a href="{{ route('client.services.emails', $service) }}" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;width:110px;height:96px;border:1px solid var(--border,#e5e7eb);border-radius:10px;text-decoration:none;color:inherit;transition:all .15s;background:var(--card-bg,#fff)" onmouseover="this.style.borderColor='var(--primary,#3b82f6)';this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border,#e5e7eb)';this.style.transform='none'">
                <i class="ri-mail-line" style="font-size:26px;color:var(--primary,#3b82f6)"></i>
                <span style="font-size:13px;font-weight:600;text-align:center">{{ __('client.hosting.email.title') }}</span>
            </a>
            @endif
        </div>
    </div>
</div>
@endif

@if(in_array(strtolower($service->status), ["active"]))
<div class="pn-actions mb-24">
    @if((($service->server?->type ?? $service->product?->server_type ?? '') === 'panelica') && $service->status === 'active')
    <a href="{{ route("client.services.login", $service) }}" class="btn btn-primary">{{ __('client.services.login_to_panel') }}</a>
    @endif
    <a href="{{ route("client.services.upgrade", $service) }}" class="btn btn-primary">{{ __('client.services.upgrade_downgrade') }}</a>
    <a href="{{ route("client.services.cancel", $service) }}" class="btn btn-danger">{{ __('client.services.request_cancellation') }}</a>
</div>
@endif

@if($service->addons && $service->addons->count())
<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.addons') }}</span></div>
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('client.services.next_due_date') }}</th><th>{{ __('common.table.status') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
            <tbody>
                @foreach($service->addons as $addon)
                <tr>
                    <td>{{ $addon->label() }}</td>
                    <td>{{ money_fmt($addon->amount) }}</td>
                    <td style="text-transform:capitalize">{{ $addon->billing_cycle }}</td>
                    <td class="text-muted text-sm">{{ $addon->next_due_date?->format(date_fmt()) ?? "-" }}</td>
                    <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ __('client.status.' . strtolower($addon->status)) }}</span></td>
                    <td style="text-align:right;">
                        @if(in_array(strtolower($addon->status), ['active', 'pending'], true))
                        <form method="POST" action="{{ route('client.services.addons.cancel', [$service, $addon]) }}"
                              onsubmit="return confirm('{{ __('client.services.addon_cancel_confirm') }}')">
                            @csrf
                            <button type="submit" class="pn-btn pn-btn-sm pn-btn-danger">{{ __('client.services.addon_cancel') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(($availableAddons ?? collect())->isNotEmpty())
<div class="pn-card" style="margin-bottom:16px;">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.services.addons_available') }}</span></div>
    <div class="pn-card-body">
        @foreach($availableAddons as $available)
        <form method="POST" action="{{ route('client.services.addons.store', $service) }}"
              style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #eee;">
            @csrf
            <input type="hidden" name="addon_id" value="{{ $available->id }}">
            <span>
                <strong>{{ $available->name }}</strong>
                @if($available->description)<br><small class="text-muted">{{ $available->description }}</small>@endif
            </span>
            <span style="white-space:nowrap;">
                {{ money_fmt($available->priceFor($service->billing_cycle ?: 'Monthly')) }}
                <button type="submit" class="pn-btn pn-btn-sm">{{ __('client.services.addon_order') }}</button>
            </span>
        </form>
        @endforeach
    </div>
</div>
@endif

@endsection
