@extends('admin.layouts.app')
@section('title', __('admin.billable_items.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.billable_items.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-bi').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.billable_items.add_item') }}</button>
</div>
<div class="card">
    @if(($billableItems ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.billable_items.no_items') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.description') }}</th><th>{{ __('common.table.amount') }}</th><th>{{ __('common.table.type') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($billableItems as $item)
        <tr>
            <td style="font-weight:600;">{{ $item->description }}</td>
            <td>${{ number_format($item->amount, 2) }}</td>
            <td style="text-transform:capitalize;">{{ $item->type ?? 'standard' }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.billable-items.destroy', $item) }}" style="display:inline;" onsubmit="return confirm('{{ __("admin.billable_items.confirm_delete") }}')">
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

<div id="modal-add-bi" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-bi').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:420px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.billable_items.add_billable_item') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-bi').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.billable-items.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><input type="text" name="description" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Amount ($)</label><input type="number" name="amount" step="0.01" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('admin.billable_items.type') }}</label><select name="type" class="form-control"><option value="standard">{{ __('admin.billable_items.type_standard') }}</option><option value="credit">{{ __('admin.billable_items.type_credit') }}</option><option value="debit">{{ __('admin.billable_items.type_debit') }}</option></select></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-bi').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.billable_items.add_item') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
