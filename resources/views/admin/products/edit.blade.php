@extends('admin.layouts.app')
@section('title', __('admin.products.edit_product') . ': ' . $product->name)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.products.edit_product') }}: {{ $product->name }}</h1>
    <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.products.back') }}</a>
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('{{ __('admin.products.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.products.delete_product') }}</button>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.update', $product) }}">
    @csrf @method('PUT')

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.products.product_details') }}</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">{{ __('admin.products.product_name') }} <span style="color:#d9534f;">*</span></label><input type="text" name="name" value="{{ $product->name }}" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.product_group') }}</label><select name="group_id" class="form-control">@foreach($groups as $g)<option value="{{ $g->id }}" {{ $product->group_id == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.type') }}</label><select name="type" class="form-control"><option value="hosting" {{ $product->type=='hosting'?'selected':'' }}>{{ __('admin.products.type_hosting') }}</option><option value="reseller" {{ $product->type=='reseller'?'selected':'' }}>{{ __('admin.products.type_reseller_label') }}</option><option value="vps" {{ $product->type=='vps'?'selected':'' }}>{{ __('admin.products.type_vps') }}</option><option value="ssl" {{ $product->type=='ssl'?'selected':'' }}>{{ __('admin.products.type_ssl') }}</option><option value="other" {{ $product->type=='other'?'selected':'' }}>{{ __('admin.products.type_other') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.payment') }}</label><select name="pay_type" class="form-control"><option value="recurring" {{ $product->pay_type=='recurring'?'selected':'' }}>{{ __('admin.products.pay_recurring') }}</option><option value="onetime" {{ $product->pay_type=='onetime'?'selected':'' }}>{{ __('admin.products.pay_onetime') }}</option><option value="free" {{ $product->pay_type=='free'?'selected':'' }}>{{ __('admin.products.pay_free') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.auto_setup') }}</label><select name="auto_setup" class="form-control"><option value="order" {{ $product->auto_setup=='order'?'selected':'' }}>{{ __('admin.products.auto_setup_order') }}</option><option value="payment" {{ $product->auto_setup=='payment'?'selected':'' }}>{{ __('admin.products.auto_setup_payment') }}</option><option value="manual" {{ $product->auto_setup=='manual'?'selected':'' }}>{{ __('admin.products.auto_setup_manual') }}</option></select></div>
                <div class="form-group"><label class="form-label">{{ __('admin.products.server_module') }}</label><input type="text" name="server_type" value="{{ $product->server_type }}" placeholder="{{ __('admin.products.module_placeholder') }}" class="form-control"></div>
                <div class="form-group" x-data="{ show: '{{ $product->type }}' === 'ssl' }" x-show="show" x-cloak><label class="form-label">{{ __('admin.products.ssl_module') }}</label><select name="ssl_module" class="form-control"><option value="">{{ __('admin.products.ssl_none') }}</option><option value="gogetssl" {{ $product->ssl_module=='gogetssl'?'selected':'' }}>{{ __('admin.products.ssl_gogetssl') }}</option></select></div>
                <div class="form-group" style="grid-column:span 2;"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="3" class="form-control">{{ $product->description }}</textarea></div>
                <div class="form-group" style="grid-column:span 2;display:flex;gap:20px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1" {{ $product->hidden?'checked':'' }}> {{ __('admin.products.hidden') }}</label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="retired" value="1" {{ $product->retired?'checked':'' }}> {{ __('admin.products.retired') }}</label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="is_featured" value="1" {{ $product->is_featured?'checked':'' }}> {{ __('admin.products.featured') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>{{ __('admin.products.pricing') }}</strong></div>
        <div class="card-body">
            @foreach($currencies as $currency)
            @php $p = $pricing[$currency->id] ?? null; @endphp
            <div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:15px;margin-bottom:12px;">
                <p style="font-weight:600;font-size:13px;margin:0 0 10px;">{{ $currency->code }} ({{ $currency->prefix }})</p>
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:8px;">
                    @foreach(['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cycle)
                    <div>
                        <label class="form-label" style="text-transform:capitalize;font-size:11px;">{{ $cycle }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="{{ $p ? $p->$cycle : -1 }}" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                    @foreach(['monthly_setup','quarterly_setup','semiannually_setup','annually_setup'] as $setup)
                    <div>
                        <label class="form-label" style="font-size:11px;">{{ str_replace('_', ' ', ucfirst($setup)) }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $setup }}]" value="{{ $p ? $p->$setup : 0 }}" class="form-control" style="font-size:12px;">
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @php $cfg = is_string($product->config_options) ? (json_decode($product->config_options, true) ?: []) : ($product->config_options ?? []); @endphp
    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Panelica Resources</strong> <span style="font-size:11px;color:#888;">&mdash; enforced cgroups/quota limits (full panel parity)</span></div>
        <div class="card-body">
            <input type="hidden" name="res_section" value="1">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;">
                <input type="checkbox" name="res_managed" value="1" {{ !empty($cfg['res_managed']) ? 'checked' : '' }}>
                <strong>Managed mode</strong> &mdash; build a matching panel plan from the limits below on provisioning
            </label>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="font-size:12px;">Or use an existing panel plan ID (leave managed unchecked)</label>
                <input type="text" name="panelica_plan_id" value="{{ $cfg['panelica_plan_id'] ?? '' }}" class="form-control" style="font-size:12px;" placeholder="d6875821-...">
            </div>
            @php $numFields = [
                'res_cpu_percent'=>['CPU Limit (%) &mdash; 100 = 1 core',100],'res_memory_mb'=>['RAM (MB)',1024],
                'res_inode_quota'=>['Inode Quota (-1 = unlimited)',-1],'res_iops'=>['IOPS (0 = unlimited)',0],
                'res_io_mbs'=>['Disk I/O (MB/s)',0],'res_disk_mb'=>['Disk (MB)',5120],
                'res_bandwidth_mb'=>['Bandwidth (MB)',51200],'res_process_limit'=>['Max Processes',100],
                'res_max_domains'=>['Max Websites',1],'res_max_subdomains'=>['Max Subdomains',10],
                'res_max_email'=>['Max Email',10],'res_max_db'=>['Max Databases',5],
                'res_max_ftp'=>['Max FTP',5],'res_max_cron'=>['Max Cron',5],
                'res_max_containers'=>['Max Containers',0],'res_network_mbit'=>['Network (Mbit/s)',0],
                'res_php_memory_mb'=>['PHP Memory (MB)',256],'res_php_exec'=>['PHP Exec (s)',30],
                'res_php_upload'=>['PHP Upload (MB)',64],
            ]; @endphp
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                @foreach($numFields as $k => $meta)
                <div>
                    <label class="form-label" style="font-size:11px;">{!! $meta[0] !!}</label>
                    <input type="number" name="{{ $k }}" value="{{ $cfg[$k] ?? $meta[1] }}" class="form-control" style="font-size:12px;">
                </div>
                @endforeach
                <div>
                    <label class="form-label" style="font-size:11px;">SSH Access</label>
                    <select name="res_ssh_level" class="form-control" style="font-size:12px;">
                        @foreach(['none','jailed','full'] as $o)<option value="{{ $o }}" {{ ($cfg['res_ssh_level'] ?? 'none')===$o?'selected':'' }}>{{ ucfirst($o) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Quota Mode</label>
                    <select name="res_quota_mode" class="form-control" style="font-size:12px;">
                        @foreach(['strict','monitor','oversell'] as $o)<option value="{{ $o }}" {{ ($cfg['res_quota_mode'] ?? 'strict')===$o?'selected':'' }}>{{ ucfirst($o) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">ModSecurity</label>
                    <select name="res_modsec" class="form-control" style="font-size:12px;">
                        <option value="on" {{ ($cfg['res_modsec'] ?? 'on')!=='off'?'selected':'' }}>On</option>
                        <option value="off" {{ ($cfg['res_modsec'] ?? 'on')==='off'?'selected':'' }}>Off</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Backups</label>
                    <select name="res_backup" class="form-control" style="font-size:12px;">
                        <option value="on" {{ ($cfg['res_backup'] ?? 'on')!=='off'?'selected':'' }}>On</option>
                        <option value="off" {{ ($cfg['res_backup'] ?? 'on')==='off'?'selected':'' }}>Off</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
    </div>
</form>
@endsection
