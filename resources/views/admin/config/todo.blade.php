@extends('admin.layouts.app')
@section('title', __('admin.todo.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.todo.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-todo').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.todo.add_task') }}</button>
</div>
<div class="card">
    @if($items->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.todo.no_tasks') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('admin.todo.task_title') }}</th>
            <th>{{ __('common.table.description') }}</th>
            <th>{{ __('common.table.status') }}</th>
            <th>{{ __('common.table.due_date') }}</th>
            <th>{{ __('admin.todo.admin') }}</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($items as $todo)
        @php
            $badgeClass = match(strtolower($todo->status ?? 'new')) {
                'completed' => 'badge-active',
                'in progress' => 'badge-pending',
                default => 'badge-open',
            };
        @endphp
        <tr>
            <td style="font-weight:600;">{{ $todo->title }}</td>
            <td style="font-size:12px;color:#777;">{{ Str::limit($todo->description ?? '', 60) ?: '-' }}</td>
            <td><span class="badge {{ $badgeClass }}">{{ ucfirst($todo->status ?? 'New') }}</span></td>
            <td style="font-size:12px;{{ ($todo->due_date && $todo->due_date->isPast() && strtolower($todo->status) !== 'completed') ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $todo->due_date?->format('d M Y') ?? '-' }}</td>
            <td style="font-size:12px;">{{ $todo->admin ?? '-' }}</td>
            <td style="text-align:right;">
                @if(strtolower($todo->status ?? '') !== 'completed')
                <form method="POST" action="{{ route('admin.config.todo.update', $todo) }}" style="display:inline;">
                    @csrf @method('PUT')
                    <input type="hidden" name="title" value="{{ $todo->title }}">
                    <input type="hidden" name="status" value="Completed">
                    <button type="submit" class="btn btn-success btn-xs">{{ __('admin.todo.complete') }}</button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.config.todo.destroy', $todo) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.todo.confirm_delete') }}')">
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

{{-- Add Task Modal --}}
<div id="modal-add-todo" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-todo').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:440px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.todo.add_task_modal') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-todo').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.todo.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.todo.task_title_label') }} *</label><input type="text" name="title" required class="form-control" placeholder="{{ __('admin.todo.placeholder_title') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control" placeholder="{{ __('admin.todo.placeholder_desc') }}"></textarea></div>
                <div class="form-group"><label class="form-label">{{ __('admin.todo.due_date') }}</label><input type="date" name="due_date" class="form-control"></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-todo').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.todo.add_task') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
