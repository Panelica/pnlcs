@extends('admin.layouts.app')
@section('title', __('admin.tickets.open_ticket'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.tickets.open_ticket') }}</h1>
    <a href="{{ route('admin.tickets.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.tickets.title') }}</a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card" style="max-width:760px;">
    <form method="POST" action="{{ route('admin.tickets.store') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="t-client">{{ __('admin.tickets.open_client') }}</label>
                @if($client)
                <div style="font-weight:600;margin-bottom:4px;">{{ $client->full_name }} <span style="font-weight:400;color:#777;">{{ $client->email }}</span></div>
                <input type="hidden" name="client" value="{{ $client->id }}">
                @else
                <input type="text" id="t-client" name="client" value="{{ old('client') }}" required class="form-control">
                <p style="font-size:12px;color:#777;margin-top:4px;">{{ __('admin.tickets.open_client_hint') }}</p>
                @endif
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="t-dept">{{ __('common.table.department') }}</label>
                    <select id="t-dept" name="department_id" required class="form-control">
                        @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="t-prio">{{ __('common.table.priority') }}</label>
                    <select id="t-prio" name="priority" class="form-control">
                        @foreach(['Low', 'Medium', 'High'] as $p)
                        <option value="{{ $p }}" @selected(old('priority', 'Medium') === $p)>{{ __('admin.tickets.priority_'.strtolower($p)) }}</option>
                        @endforeach
                    </select>
                </div>
                @if($services->isNotEmpty())
                <div class="form-group">
                    <label class="form-label" for="t-svc">{{ __('admin.tickets.service') }}</label>
                    <select id="t-svc" name="related_service" class="form-control">
                        <option value="">{{ __('admin.tickets.open_no_service') }}</option>
                        @foreach($services as $s)
                        <option value="{{ $s->id }}" @selected(old('related_service') == $s->id)>{{ $s->product?->name }}@if($s->domain) · {{ $s->domain }}@endif</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <div class="form-group">
                <label class="form-label" for="t-subject">{{ __('common.table.subject') }}</label>
                <input type="text" id="t-subject" name="subject" value="{{ old('subject') }}" required maxlength="255" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="t-message">{{ __('admin.tickets.open_message') }}</label>
                <textarea id="t-message" name="message" rows="8" required class="form-control">{{ old('message') }}</textarea>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.tickets.open_ticket') }}</button>
        </div>
    </form>
</div>
@endsection
