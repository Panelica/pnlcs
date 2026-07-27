@extends("admin.layouts.app")
@section("title", __("admin.client_groups"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.client_groups.title') }}</h1>
    <button type="button" onclick="openModal('add-group')" class="btn btn-primary btn-sm">+ {{ __('admin.client_groups.add_group') }}</button>
</div>
<div class="card">
    @if(($groups ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">
        <p style="font-size:16px;margin-bottom:5px;">{{ __('admin.client_groups.no_groups') }}</p>
        <p style="font-size:12px;">{{ __('admin.client_groups.description') }}</p>
    </div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.client_groups.group_name') }}</th><th>{{ __('admin.client_groups.color') }}</th><th>{{ __('admin.client_groups.clients') }}</th><th>{{ __('admin.client_groups.discount_pct') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($groups as $group)
        <tr>
            <td style="font-weight:600;">{{ $group->name }}</td>
            <td><span style="display:inline-block;width:20px;height:20px;border-radius:3px;background:{{ $group->color ?? "#ccc" }};"></span></td>
            <td>{{ $group->clients_count ?? 0 }}</td>
            <td>{{ $group->discount_percent ?? 0 }}%</td>
            <td style="text-align:right;">
                <button type="button" onclick="openModal('edit-group-{{ $group->id }}')" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</button>
                <form method="POST" action="{{ route("admin.config.client-groups.destroy", $group) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.client_groups.confirm_delete") }}')">@csrf @method("DELETE")<button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button></form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<x-modal name="add-group" title="{{ __('admin.client_groups.add_group') }}" maxWidth="sm">
    <form method="POST" action="{{ route("admin.config.client-groups.store") }}">
        @csrf
        <div class="form-group"><label class="form-label">{{ __('admin.client_groups.group_name') }}</label><input type="text" name="name" required class="form-control" placeholder="{{ __('admin.client_groups.name_placeholder') }}"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.client_groups.color') }}</label><input type="color" name="color" value="#405189" class="form-control" style="height:38px;padding:3px;"></div>
        <div class="form-group"><label class="form-label">{{ __('admin.client_groups.discount_pct') }}</label><input type="number" name="discount_percent" value="0" min="0" max="100" step="0.01" class="form-control"></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('add-group')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.client_groups.create_group') }}</button>
        </div>
    </form>
</x-modal>

@endsection
