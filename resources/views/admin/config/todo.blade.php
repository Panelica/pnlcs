@extends('admin.layouts.app')
@section('title', 'To-Do List')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>To-Do List</h1>
    <button type="button" onclick="document.getElementById('modal-add-todo').style.display='flex'" class="btn btn-primary btn-sm">+ Add Task</button>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div class="card">
    @if(($todos ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No tasks. You are all caught up!</div>
    @else
    <table class="data-table">
        <thead><tr><th style="width:30px;"></th><th>Task</th><th>Due Date</th><th>Admin</th><th>Priority</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($todos as $todo)
        <tr style="{{ $todo->completed ? 'opacity:0.5;' : '' }}">
            <td style="text-align:center;">
                <form method="POST" action="{{ route('admin.config.todo.toggle', $todo) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <button type="submit" style="background:none;border:none;cursor:pointer;font-size:16px;">{{ $todo->completed ? '&#9989;' : '&#9744;' }}</button>
                </form>
            </td>
            <td style="{{ $todo->completed ? 'text-decoration:line-through;' : 'font-weight:600;' }}">{{ $todo->title }}</td>
            <td style="font-size:12px;{{ ($todo->due_date?->isPast() && !$todo->completed) ? 'color:#d9534f;font-weight:600;' : '' }}">{{ $todo->due_date?->format('d M Y') ?? '&mdash;' }}</td>
            <td style="font-size:12px;">{{ $todo->admin ?? 'Any' }}</td>
            <td><span style="font-size:11px;text-transform:capitalize;">{{ $todo->priority ?? 'Normal' }}</span></td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.todo.destroy', $todo) }}" style="display:inline;" onsubmit="return confirm('Delete task?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div id="modal-add-todo" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-todo').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:440px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">Add Task</h4>
            <button type="button" onclick="document.getElementById('modal-add-todo').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.todo.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">Task Title</label><input type="text" name="title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">Due Date <small style="color:#999;">(optional)</small></label><input type="date" name="due_date" class="form-control"></div>
                <div class="form-group"><label class="form-label">Priority</label><select name="priority" class="form-control"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-todo').style.display='none'" class="btn btn-default btn-sm">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Task</button>
            </div>
        </form>
    </div>
</div>
@endsection
