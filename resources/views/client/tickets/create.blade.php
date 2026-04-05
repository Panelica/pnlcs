@extends("client.layouts.app")
@section("title", __("client.open_support_ticket"))
@section("content")

<a href="{{ route("client.tickets.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.tickets.back_to_tickets') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.tickets.open_support_ticket') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.tickets.team_respond') }}</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start"><div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.tickets.new_support_request') }}</span></div>
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
                    <label class="form-label" for="department_id">{{ __('client.contact.department') }} <span class="req">*</span></label>
                    <select id="department_id" name="department_id" required class="form-control">
                        <option value="">-- {{ __('client.contact.select_department') }} --</option>
                        @foreach($departments as $d)
                        <option value="{{ $d->id }}" {{ old("department_id") == $d->id ? "selected" : "" }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">{{ __('client.tickets.priority') }}</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low" {{ old("priority") == "low" ? "selected" : "" }}>{{ __('client.tickets.low') }}</option>
                        <option value="medium" {{ old("priority", "medium") == "medium" ? "selected" : "" }}>{{ __('client.tickets.medium') }}</option>
                        <option value="high" {{ old("priority") == "high" ? "selected" : "" }}>{{ __('client.tickets.high') }}</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="subject">{{ __('common.form.subject') }}<span class="req">*</span></label>
                <input type="text" id="subject" name="subject" value="{{ old("subject") }}" required class="form-control" placeholder="{{ __('client.tickets.subject_placeholder') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="related_service">{{ __('client.tickets.related_service') }} <span style="font-weight:400;color:var(--muted)">({{ __('client.form.optional') }})</span></label>
                <select id="related_service" name="related_service" class="form-control">
                    <option value="">-- {{ __('client.tickets.none') }} --</option>
                    @if(isset($services))
                        @foreach($services as $svc)
                        <option value="{{ $svc->id }}">{{ $svc->product?->name ?? "Service" }}{{ $svc->domain ? " (".$svc->domain.")" : "" }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="message">{{ __('common.form.message') }}<span class="req">*</span></label>
                <textarea id="message" name="message" rows="9" required class="form-control" placeholder="{{ __('client.tickets.message_placeholder') }}">{{ old("message") }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('client.tickets.attachment') }} <span style="font-weight:400;color:var(--muted)">({{ __('client.form.optional') }}, {{ __('client.tickets.max_10mb') }})</span></label>
                <input type="file" name="attachment" accept=".jpg,.png,.gif,.pdf,.doc,.docx,.txt,.zip" class="form-control" style="padding:6px 10px;">
            </div>
            <div class="flex gap-8">
                <button type="submit" class="btn btn-primary">{{ __('client.tickets.submit_ticket') }}</button>
                <a href="{{ route("client.tickets.index") }}" class="btn btn-outline">{{ __('common.actions.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

</div>
<div>
<div class="pn-card">
<div class="pn-card-header"><span class="pn-card-title">{{ __('client.tickets.before_submit') }}</span></div>
<div class="pn-card-body" style="font-size:13px;color:var(--muted)">
<p style="margin-bottom:12px">{{ __('client.tickets.check_resources') }}</p>
<a href="/client/knowledgebase" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--primary-light);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;margin-bottom:8px">{{ __('client.nav.knowledge_base') }}</a>
<a href="/client/announcements" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--primary-light);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;margin-bottom:8px">{{ __('client.nav.announcements') }}</a>
</div>
</div>
<div class="pn-card" style="margin-top:16px">
<div class="pn-card-header"><span class="pn-card-title">{{ __('client.tickets.response_times') }}</span></div>
<div class="pn-card-body" style="font-size:13px">
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">{{ __('client.tickets.low_priority') }}</span><strong>24 hours</strong></div>
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span style="color:var(--muted)">{{ __('client.tickets.medium_priority') }}</span><strong>12 hours</strong></div>
<div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:var(--muted)">{{ __('client.tickets.high_priority') }}</span><strong style="color:#ef4444">4 hours</strong></div>
</div>
</div>
</div>
</div>
@endsection
