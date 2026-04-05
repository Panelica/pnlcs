@extends("client.layouts.app")
@section("title", __("client.open_support_ticket"))
@section("content")

<a href="{{ route("client.tickets.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Back to My Tickets
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Open a Support Ticket</h1>
        <p class="pn-page-subtitle">Our team will respond as soon as possible.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start"><div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">New Support Request</span></div>
    <div class="pn-card-body">
        @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route("client.tickets.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="department_id">Department <span class="req">*</span></label>
                    <select id="department_id" name="department_id" required class="form-control">
                        <option value="">-- Select Department --</option>
                        @foreach($departments as $d)
                        <option value="{{ $d->id }}" {{ old("department_id") == $d->id ? "selected" : "" }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low" {{ old("priority") == "low" ? "selected" : "" }}>Low</option>
                        <option value="medium" {{ old("priority", "medium") == "medium" ? "selected" : "" }}>Medium</option>
                        <option value="high" {{ old("priority") == "high" ? "selected" : "" }}>High</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="subject">{{ __('common.form.subject') }}<span class="req">*</span></label>
                <input type="text" id="subject" name="subject" value="{{ old("subject") }}" required class="form-control" placeholder="Brief summary of your issue">
            </div>
            <div class="form-group">
                <label class="form-label" for="related_service">Related Service <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                <select id="related_service" name="related_service" class="form-control">
                    <option value="">-- None --</option>
                    @if(isset($services))
                        @foreach($services as $svc)
                        <option value="{{ $svc->id }}">{{ $svc->product?->name ?? "Service" }}{{ $svc->domain ? " (".$svc->domain.")" : "" }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="message">{{ __('common.form.message') }}<span class="req">*</span></label>
                <textarea id="message" name="message" rows="9" required class="form-control" placeholder="Please describe your issue in detail...">{{ old("message") }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Attachment <span style="font-weight:400;color:var(--muted)">(optional, max 10MB)</span></label>
                <input type="file" name="attachment" accept=".jpg,.png,.gif,.pdf,.doc,.docx,.txt,.zip" class="form-control" style="padding:6px 10px;">
            </div>
            <div class="flex gap-8">
                <button type="submit" class="btn btn-primary">Submit Ticket</button>
                <a href="{{ route("client.tickets.index") }}" class="btn btn-outline">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

</div>
<div>
<div class="pn-card">
<div class="pn-card-header"><span class="pn-card-title">Before You Submit</span></div>
<div class="pn-card-body" style="font-size:13px;color:var(--muted)">
<p style="margin-bottom:12px">Please check these resources first:</p>
<a href="/client/knowledgebase" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--primary-light);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;margin-bottom:8px">Knowledge Base</a>
<a href="/client/announcements" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--primary-light);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;margin-bottom:8px">Announcements</a>
</div>
</div>
<div class="pn-card" style="margin-top:16px">
<div class="pn-card-header"><span class="pn-card-title">Response Times</span></div>
<div class="pn-card-body" style="font-size:13px">
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Low Priority</span><strong>24 hours</strong></div>
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Medium Priority</span><strong>12 hours</strong></div>
<div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:var(--muted)">High Priority</span><strong style="color:#ef4444">4 hours</strong></div>
</div>
</div>
</div>
</div>
@endsection
