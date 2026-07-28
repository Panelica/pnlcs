@extends("admin.layouts.app")
@section("title", __("admin.configurable_options"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.config_options.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-group').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.config_options.add_group') }}</button>
</div>

@if(($groups ?? collect())->isEmpty())
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.config_options.no_groups_message') }}</div>
</div>
@else
@foreach($groups as $group)
<div class="card" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8f9fa;cursor:pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
        <div>
            <strong style="font-size:15px;">{{ $group->name }}</strong>
            @if($group->description)
            <span style="color:#777;font-size:12px;margin-left:8px;">{{ $group->description }}</span>
            @endif
            <span class="badge badge-active" style="margin-left:8px;">{{ $group->options->count() }} {{ __('admin.config_options.options_count') }}</span>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="button" onclick="event.stopPropagation(); document.getElementById('modal-edit-group-{{ $group->id }}').style.display='flex'" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
            <form method="POST" action="{{ route('admin.config.config-option-groups.destroy', $group->id) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.config_options.confirm_delete_group") }} {{ $group->name }} and all its options?')">
                @csrf @method("DELETE")
                <button type="submit" class="btn btn-danger btn-xs" onclick="event.stopPropagation();">{{ __('common.actions.delete') }}</button>
            </form>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        {{-- Which products offer this group. Without a link the customer never
             sees these options, however many are defined. --}}
        <div style="padding:12px 16px;border-bottom:1px solid #eee;background:#fcfcfd;">
            <form method="POST" action="{{ route('admin.config.config-option-groups.link', $group->id) }}"
                  style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                @csrf
                @php $linked = $group->productLinks->pluck('product_id')->all(); @endphp
                <div class="form-group" style="margin:0;flex:1;min-width:260px;">
                    <label class="form-label" style="font-size:11px;">{{ __('admin.config_options.applies_to_products') }}</label>
                    <select name="product_ids[]" multiple size="4" class="form-control" style="font-size:12px;">
                        @foreach($products ?? [] as $p)
                        <option value="{{ $p->id }}" @selected(in_array($p->id, $linked, true))>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-default btn-sm">{{ __('common.actions.save') }}</button>
            </form>
        </div>

        @if($group->options->isEmpty())
        <div style="padding:20px;text-align:center;color:#999;font-size:13px;">{{ __('admin.config_options.no_options') }}</div>
        @else
        <table class="data-table" style="margin:0;">
            <thead><tr><th>{{ __('admin.config_options.option_name') }}</th><th>{{ __('common.table.type') }}</th><th>{{ __('admin.config_options.sub_options') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
            <tbody>
            @foreach($group->options as $option)
            <tr>
                <td style="font-weight:600;">{{ $option->option_name }}</td>
                <td><span class="badge badge-active" style="text-transform:capitalize;">{{ $option->option_type }}</span></td>
                <td>
                    @if($option->subs->isEmpty())
                    <span style="color:#999;font-size:12px;">{{ __('admin.config_options.none') }}</span>
                    @else
                    @foreach($option->subs as $sub)
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#f0f0f0;padding:2px 8px;border-radius:3px;font-size:12px;margin:1px;">
                        {{ $sub->option_name }}
                        <form method="POST" action="{{ route('admin.config.config-option-subs.destroy', $sub->id) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.config_options.confirm_delete_sub") }}')">
                            @csrf @method("DELETE")
                            <button type="submit" style="background:none;border:none;color:#d9534f;cursor:pointer;font-size:11px;padding:0;">&times;</button>
                        </form>
                    </span>
                    @endforeach
                    @endif
                </td>
                <td style="text-align:right;white-space:nowrap;">
                    <button type="button" onclick="document.getElementById('modal-add-sub-{{ $option->id }}').style.display='flex'" class="btn btn-success btn-xs">+ {{ __('admin.config_options.add_sub') }}</button>
                    <form method="POST" action="{{ route('admin.config.config-options.destroy', $option->id) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.config_options.confirm_delete_option") }} {{ $option->option_name }}?')">
                        @csrf @method("DELETE")
                        <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
        <div style="padding:10px 16px;border-top:1px solid #eee;">
            <form method="POST" action="{{ route('admin.config.config-options.store') }}" style="display:flex;gap:8px;align-items:flex-end;">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">
                <div style="flex:1;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.option_name') }}</label><input type="text" name="option_name" required class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                <div><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.type') }}</label>
                    <select name="option_type" class="form-control" style="font-size:12px;padding:5px 8px;">
                        <option value="dropdown">{{ __('admin.config_options.type_dropdown') }}</option><option value="radio">{{ __('admin.config_options.type_radio') }}</option><option value="checkbox">{{ __('admin.config_options.type_checkbox') }}</option><option value="quantity">{{ __('admin.config_options.type_quantity') }}</option><option value="text">{{ __('admin.config_options.type_text') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-xs">{{ __('admin.config_options.add_option') }}</button>
            </form>
        </div>
    </div>
</div>

{{-- Edit Group Modal --}}
<div id="modal-edit-group-{{ $group->id }}" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.config_options.edit_group') }}</h4>
            <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.config-option-groups.update', $group->id) }}">
            @csrf @method("PUT")
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.config_options.group_name') }}</label><input type="text" name="name" value="{{ $group->name }}" required class="form-control"></div>
                <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" class="form-control" rows="2">{{ $group->description }}</textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.config_options.update_group') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Sub-Option Modals --}}
@foreach($group->options as $option)
<div id="modal-add-sub-{{ $option->id }}" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:400px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.config_options.add_sub_option_to') }} "{{ $option->option_name }}"</h4>
            <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.config-option-subs.store') }}">
            @csrf
            <input type="hidden" name="config_id" value="{{ $option->id }}">
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.config_options.sub_option_name') }}</label><input type="text" name="option_name" required class="form-control" placeholder="{{ __('admin.config_options.sub_option_placeholder') }}"></div>
                <div style="margin-top:10px;">
                    <div style="font-size:12px;font-weight:600;margin-bottom:6px;">{{ __('admin.config_options.pricing') }}</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_monthly') }}</label><input type="number" step="0.01" min="0" name="monthly" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_quarterly') }}</label><input type="number" step="0.01" min="0" name="quarterly" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_semiannually') }}</label><input type="number" step="0.01" min="0" name="semiannually" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_annually') }}</label><input type="number" step="0.01" min="0" name="annually" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_biennially') }}</label><input type="number" step="0.01" min="0" name="biennially" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label" style="font-size:11px;">{{ __('admin.config_options.price_triennially') }}</label><input type="number" step="0.01" min="0" name="triennially" value="0" class="form-control" style="font-size:12px;padding:5px 8px;"></div>
                    </div>
                </div>
                <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('admin.config_options.sort_order') }}</label><input type="number" name="sort_order" value="0" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.config_options.add_sub_option') }}</button>
            </div>
        </form>
    </div>
</div>
@endforeach

@endforeach
@endif

{{-- Add Group Modal --}}
<div id="modal-add-group" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-group').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:450px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.config_options.add_config_option_group') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-group').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.config-option-groups.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.config_options.group_name') }}</label><input type="text" name="name" required class="form-control" placeholder="{{ __('admin.config_options.group_name_placeholder') }}"></div>
                <div class="form-group" style="margin-top:12px;"><label class="form-label">{{ __('admin.config_options.description_optional') }}</label><textarea name="description" class="form-control" rows="2" placeholder="{{ __('admin.config_options.description_placeholder') }}"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-group').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.config_options.create_group') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection
