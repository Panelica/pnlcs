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
                <li><span class="key">{{ __('client.services.amount') }}</span><span class="val">${{ number_format($service->amount, 2) }} / {{ $service->billing_cycle }}</span></li>
                <li><span class="key">{{ __('client.services.next_due_date') }}</span><span class="val">{{ $service->next_due_date?->format("d M Y") ?? "N/A" }}</span></li>
                <li><span class="key">{{ __('client.services.registration_date') }}</span><span class="val">{{ $service->registration_date?->format("d M Y") ?? "N/A" }}</span></li>
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
    fetch("{{ route('client.services.usage', $service) }}",{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();}).then(function(u){
        if(!u||!u.available){el.innerHTML='<span class="text-muted">Live usage is not available right now.</span>';return;}
        var html='';
        if(u.disk) html+=bar("{{ __('client.services.disk_usage') }}",u.disk.used_mb||0,u.disk.quota_mb||0,'MB');
        if(u.bandwidth) html+=bar("{{ __('client.services.bandwidth_usage') }}",u.bandwidth.used_mb||0,0,'MB');
        if(u.counts){html+='<div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13px;margin-top:6px">';
          [['domains','Websites'],['databases','Databases'],['emails','Email'],['ftp','FTP']].forEach(function(p){
            html+='<span class="text-muted"><strong>'+(u.counts[p[0]]||0)+'</strong> '+p[1]+'</span>';});
          html+='</div>';}
        el.innerHTML=html||'<span class="text-muted">No usage data yet.</span>';
      }).catch(function(){el.innerHTML='<span class="text-muted">Live usage is not available right now.</span>';});
})();
</script>
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
            <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.billing_cycle') }}</th><th>{{ __('client.services.next_due_date') }}</th><th>{{ __('common.table.status') }}</th></tr></thead>
            <tbody>
                @foreach($service->addons as $addon)
                <tr>
                    <td>{{ __('client.services.addon_prefix', ['id' => $addon->addon_id ?? $addon->id]) }}</td>
                    <td>${{ number_format($addon->amount, 2) }}</td>
                    <td style="text-transform:capitalize">{{ $addon->billing_cycle }}</td>
                    <td class="text-muted text-sm">{{ $addon->next_due_date?->format("d M Y") ?? "-" }}</td>
                    <td><span class="badge badge-{{ strtolower($addon->status) }}">{{ __('client.status.' . strtolower($addon->status)) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
