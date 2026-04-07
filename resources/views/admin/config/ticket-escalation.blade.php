@extends("admin.layouts.app")
@section("title", __("admin.ticket_escalation_rules"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.ticket_escalation.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-rule').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.ticket_escalation.add_rule') }}</button>
</div>

<div class="card">
    @if(($rules ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.ticket_escalation.no_rules') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.name') }}</th><th>{{ __('admin.ticket_escalation.time_threshold') }}</th><th>{{ __('admin.ticket_escalation.departments') }}</th><th>{{ __('admin.ticket_escalation.priorities') }}</th><th>{{ __('admin.ticket_escalation.assign_to') }}</th><th>{{ __('admin.ticket_escalation.new_priority') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($rules as $rule)
        <tr>
            <td style="font-weight:600;">{{ $rule->name }}</td>
            <td>{{ $rule->time_elapsed }} min</td>
            <td>
                @php
                    $deptNames = [];
                    if (is_array($rule->departments) && count($rule->departments) > 0) {
                        $deptNames = \App\Models\TicketDepartment::whereIn("id", $rule->departments)->pluck("name")->toArray();
                    }
                @endphp
                {{ $deptNames ? implode(", ", $deptNames) : __("admin.tax.all") }}
            </td>
            <td>
                @if(is_array($rule->priorities) && count($rule->priorities) > 0)
                    @foreach($rule->priorities as $p)
                    <span class="badge badge-active" style="text-transform:capitalize;">{{ $p }}</span>
                    @endforeach
                @else
                    All
                @endif
            </td>
            <td>
                @if($rule->flag_to)
                    @php $admin = \App\Models\Admin::find($rule->flag_to); @endphp
                    {{ $admin ? $admin->first_name . " " . $admin->last_name : "Admin #" . $rule->flag_to }}
                @else
                    -
                @endif
            </td>
            <td style="text-transform:capitalize;">{{ $rule->new_priority ?? "-" }}</td>
            <td style="text-align:right;white-space:nowrap;">
                <button type="button" onclick="document.getElementById('modal-edit-rule-{{ $rule->id }}').style.display='flex'" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.ticket-escalation.destroy', $rule->id) }}" style="display:inline;" onsubmit="return confirm('{{ __(\"admin.ticket_escalation.confirm_delete\") }}')">
                    @csrf @method("DELETE")
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>

        {{-- Edit Rule Modal --}}
        <div id="modal-edit-rule-{{ $rule->id }}" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
            <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="this.parentElement.style.display='none'"></div>
            <div style="position:relative;background:#fff;border-radius:4px;width:550px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
                <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
                    <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_escalation.edit_rule_title', ['name' => $rule->name]) }}</h4>
                    <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.config.ticket-escalation.update', $rule->id) }}">
                    @csrf @method("PUT")
                    <div style="padding:20px;">
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.rule_name') }}</label><input type="text" name="name" value="{{ $rule->name }}" required class="form-control"></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                            <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.time_threshold_minutes') }}</label><input type="number" name="time_elapsed" value="{{ $rule->time_elapsed }}" required min="1" class="form-control"></div>
                            <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.new_priority') }}</label>
                                <select name="new_priority" class="form-control">
                                    <option value="">{{ __('admin.ticket_escalation.no_change') }}</option>
                                    <option value="low" {{ $rule->new_priority == "low" ? "selected" : "" }}>{{ __('admin.ticket_escalation.priority_low') }}</option>
                                    <option value="medium" {{ $rule->new_priority == "medium" ? "selected" : "" }}>{{ __('admin.ticket_escalation.priority_medium') }}</option>
                                    <option value="high" {{ $rule->new_priority == "high" ? "selected" : "" }}>{{ __('admin.ticket_escalation.priority_high') }}</option>
                                    <option value="urgent" {{ $rule->new_priority == "urgent" ? "selected" : "" }}>{{ __('admin.ticket_escalation.priority_urgent') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label class="form-label">{{ __('admin.ticket_escalation.assign_to_admin') }}</label>
                            <select name="flag_to" class="form-control">
                                <option value="">{{ __('admin.ticket_escalation.no_change') }}</option>
                                @foreach($admins as $admin)
                                <option value="{{ $admin->id }}" {{ $rule->flag_to == $admin->id ? "selected" : "" }}>{{ $admin->first_name }} {{ $admin->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label class="form-label">{{ __('admin.ticket_escalation.move_to_department') }}</label>
                            <select name="new_department_id" class="form-control">
                                <option value="">{{ __('admin.ticket_escalation.no_change') }}</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $rule->new_department_id == $dept->id ? "selected" : "" }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label class="form-label">{{ __('admin.ticket_escalation.auto_reply_message') }}</label>
                            <textarea name="add_reply" class="form-control" rows="2" placeholder="Leave empty for no auto-reply">{{ $rule->add_reply }}</textarea>
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="notify" value="1" {{ $rule->notify ? "checked" : "" }}> {{ __('admin.ticket_escalation.send_notification') }}</label>
                        </div>
                    </div>
                    <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                        <button type="button" onclick="this.closest('[id^=modal]').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.ticket_escalation.update_rule') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div style="margin-top:12px;padding:12px 16px;background:#f0f7ff;border:1px solid #b3d4fc;border-radius:4px;font-size:13px;color:#31708f;">
    <i class="fas fa-info-circle"></i> {{ __('admin.ticket_escalation.info_text') }}
</div>

{{-- Add Rule Modal --}}
<div id="modal-add-rule" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-rule').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:550px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_escalation.add_rule') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-rule').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.ticket-escalation.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.rule_name') }}</label><input type="text" name="name" required class="form-control" placeholder="e.g. High Priority - 1 Hour"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.time_threshold_minutes') }}</label><input type="number" name="time_elapsed" required min="1" value="60" class="form-control"></div>
                    <div class="form-group"><label class="form-label">{{ __('admin.ticket_escalation.new_priority') }}</label>
                        <select name="new_priority" class="form-control">
                            <option value="">{{ __('admin.ticket_escalation.no_change') }}</option>
                            <option value="low">{{ __('admin.ticket_escalation.priority_low') }}</option>
                            <option value="medium">{{ __('admin.ticket_escalation.priority_medium') }}</option>
                            <option value="high">{{ __('admin.ticket_escalation.priority_high') }}</option>
                            <option value="urgent">{{ __('admin.ticket_escalation.priority_urgent') }}</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label class="form-label">{{ __('admin.ticket_escalation.assign_to_admin') }}</label>
                    <select name="flag_to" class="form-control">
                        <option value="">{{ __('admin.ticket_escalation.no_assignment') }}</option>
                        @foreach($admins as $admin)
                        <option value="{{ $admin->id }}">{{ $admin->first_name }} {{ $admin->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label class="form-label">{{ __('admin.ticket_escalation.move_to_department') }}</label>
                    <select name="new_department_id" class="form-control">
                        <option value="">{{ __('admin.ticket_escalation.no_change') }}</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label class="form-label">{{ __('admin.ticket_escalation.auto_reply_message') }}</label>
                    <textarea name="add_reply" class="form-control" rows="2" placeholder="Leave empty for no auto-reply"></textarea>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="notify" value="1"> {{ __('admin.ticket_escalation.send_notification') }}</label>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-rule').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.ticket_escalation.create_rule') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection
