@extends('client.layouts.app')
@section('title', 'Open Support Ticket')
@section('content')

<div class="page-header">
    <h1>Open New Ticket</h1>
    <a href="{{ route('client.tickets.index') }}" class="btn btn-default btn-sm">&larr; My Tickets</a>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-header">Submit a Support Request</div>
    <div class="card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.tickets.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="department_id">Department <span style="color:#c43c35;">*</span></label>
                <select id="department_id" name="department_id" required class="form-control">
                    <option value="">-- Select Department --</option>
                    @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ old('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="subject">Subject <span style="color:#c43c35;">*</span></label>
                <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required class="form-control" placeholder="Brief summary of your issue">
            </div>
            <div class="form-group">
                <label class="form-label" for="related_service">Related Service <span style="color:#999; font-weight:400;">(optional)</span></label>
                <select id="related_service" name="related_service" class="form-control">
                    <option value="">-- None --</option>
                    @if(isset($services))
                        @foreach($services as $svc)
                        <option value="{{ $svc->id }}">{{ $svc->product->name ?? 'Service' }} {{ $svc->domain ? '('. $svc->domain .')' : '' }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="priority">Priority</label>
                <select id="priority" name="priority" class="form-control">
                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="message">Message <span style="color:#c43c35;">*</span></label>
                <textarea id="message" name="message" rows="8" required class="form-control" placeholder="Please describe your issue in detail...">{{ old('message') }}</textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Submit Ticket</button>
                <a href="{{ route('client.tickets.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
