@extends('admin.layouts.app')
@section('title', __('admin.ticket_departments.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.ticket_departments.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-dept').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.ticket_departments.add_department') }}</button>
</div>
<div class="card">
    @if(($departments ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.ticket_departments.no_departments') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.ticket_departments.department_name') }}</th><th>{{ __('common.table.email') }}</th><th>{{ __('admin.ticket_departments.public') }}</th><th>{{ __('admin.ticket_departments.tickets') }}</th><th>{{ __('admin.ticket_departments.mail_import') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($departments as $dept)
        <tr>
            <td style="font-weight:600;">{{ $dept->name }}</td>
            <td style="font-size:12px;">{{ $dept->email ?? '-' }}</td>
            <td>{{ $dept->hidden ? __('common.no') : __('common.yes') }}</td>
            <td>{{ $dept->tickets_count ?? 0 }}</td>
            <td style="font-size:12px;">
                @if($dept->import_active)
                <span style="color:#28a745;font-weight:600;">{{ __('common.yes') }}</span>
                @if($dept->last_import_at)<br><small style="color:#999;">{{ $dept->last_import_at->diffForHumans() }}</small>@endif
                @else
                <span style="color:#999;">{{ __('common.no') }}</span>
                @endif
            </td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-default btn-xs"
                    onclick="openEditDept({{ json_encode(['id'=>$dept->id,'name'=>$dept->name,'email'=>$dept->email,'description'=>$dept->description,'hidden'=>$dept->hidden,'import_active'=>$dept->import_active,'import_protocol'=>$dept->import_protocol,'import_host'=>$dept->import_host,'import_port'=>$dept->import_port,'import_encryption'=>$dept->import_encryption,'import_username'=>$dept->import_username,'import_folder'=>$dept->import_folder,'import_delete'=>$dept->import_delete,'import_allow_unknown'=>$dept->import_allow_unknown]) }})">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route('admin.config.ticket-departments.destroy', $dept) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.ticket_departments.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-dept" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-dept').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:460px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_departments.add_department') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-dept').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.ticket-departments.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.department_name') }}</label><input type="text" name="name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.email_address') }}<small style="color:#999;">{{ __('admin.ticket_departments.email_hint_text') }}</small></label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control"></textarea></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1"> {{ __('admin.ticket_departments.hidden_from_clients') }}</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-dept').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.ticket_departments.add_department') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-dept" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-dept').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:460px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_departments.edit_department') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-dept').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" id="edit-dept-form" action="">
            @csrf @method('PUT')
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.department_name') }}</label><input type="text" name="name" id="ed-name" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.email_address') }}</label><input type="email" name="email" id="ed-email" class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" id="ed-desc" rows="2" class="form-control"></textarea></div>
                <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="hidden" value="1" id="ed-hidden"> {{ __('admin.ticket_departments.hidden_short') }}</label></div>

                <div style="border-top:1px solid #e5e5e5;margin:14px 0 12px;padding-top:12px;">
                    <label style="font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="import_active" value="1" id="ed-imp-active"> {{ __('admin.ticket_departments.import_enable') }}
                    </label>
                    <small style="color:#999;">{{ __('admin.ticket_departments.import_hint') }}</small>
                </div>
                <div id="ed-imp-fields">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_protocol') }}</label>
                            <select name="import_protocol" id="ed-imp-protocol" class="form-control"><option value="imap">IMAP</option><option value="pop3">POP3</option></select></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_encryption') }}</label>
                            <select name="import_encryption" id="ed-imp-enc" class="form-control"><option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none">{{ __('common.no') }}</option></select></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_host') }}</label>
                            <input type="text" name="import_host" id="ed-imp-host" class="form-control" placeholder="mail.example.com"></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_port') }}</label>
                            <input type="number" name="import_port" id="ed-imp-port" class="form-control" placeholder="993"></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_username') }}</label>
                            <input type="text" name="import_username" id="ed-imp-user" class="form-control" autocomplete="off"></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_password') }}</label>
                            <input type="password" name="import_password" class="form-control" autocomplete="new-password" placeholder="{{ __('admin.ticket_departments.import_password_keep') }}"></div>
                        <div class="form-group"><label class="form-label">{{ __('admin.ticket_departments.import_folder') }}</label>
                            <input type="text" name="import_folder" id="ed-imp-folder" class="form-control" placeholder="INBOX"></div>
                    </div>
                    <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="import_delete" value="1" id="ed-imp-delete"> {{ __('admin.ticket_departments.import_delete') }}</label></div>
                    <div class="form-group"><label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="import_allow_unknown" value="1" id="ed-imp-unknown"> {{ __('admin.ticket_departments.import_allow_unknown') }}</label></div>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-dept').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDept(d) {
    document.getElementById('edit-dept-form').action = '/admin/config/ticket-departments/' + d.id;
    document.getElementById('ed-name').value = d.name;
    document.getElementById('ed-email').value = d.email || '';
    document.getElementById('ed-desc').value = d.description || '';
    document.getElementById('ed-hidden').checked = !!d.hidden;
    document.getElementById('ed-imp-active').checked = !!d.import_active;
    document.getElementById('ed-imp-protocol').value = d.import_protocol || 'imap';
    document.getElementById('ed-imp-enc').value = d.import_encryption || 'ssl';
    document.getElementById('ed-imp-host').value = d.import_host || '';
    document.getElementById('ed-imp-port').value = d.import_port || '';
    document.getElementById('ed-imp-user').value = d.import_username || '';
    document.getElementById('ed-imp-folder').value = d.import_folder || 'INBOX';
    document.getElementById('ed-imp-delete').checked = !!d.import_delete;
    document.getElementById('ed-imp-unknown').checked = !!d.import_allow_unknown;
    document.getElementById('modal-edit-dept').style.display = 'flex';
}
</script>
@endsection
