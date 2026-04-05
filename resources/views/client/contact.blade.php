@extends("client.layouts.app")
@section("title", __("client.contact.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Contact Us</h1>
        <p class="pn-page-subtitle">Send us a message and we will get back to you.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:32px;max-width:100%">
    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">Send a Message</span></div>
        <div class="pn-card-body">
            @if($errors->any())
            <div class="pn-alert pn-alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif

            <form method="POST" action="{{ route("client.contact.submit") }}">
                @csrf
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="name">Your Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old("name", auth()->user()?->full_name) }}" required maxlength="100" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">{{ __('common.form.email_address') }}<span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old("email", auth()->user()?->email) }}" required maxlength="200" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="department_id">Department <span class="req">*</span></label>
                    <select id="department_id" name="department_id" required class="form-control">
                        <option value="">-- Select a department --</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old("department_id") == $dept->id ? "selected" : "" }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="subject">{{ __('common.form.subject') }}<span class="req">*</span></label>
                    <input type="text" id="subject" name="subject" value="{{ old("subject") }}" required maxlength="200" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="message">{{ __('common.form.message') }}<span class="req">*</span></label>
                    <textarea id="message" name="message" rows="7" required maxlength="5000" class="form-control" placeholder="How can we help you?">{{ old("message") }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>

    <div>
        <div class="pn-card mb-16">
            <div class="pn-card-body">
                <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px">Prefer tickets?</div>
                <p class="text-muted text-sm" style="margin-bottom:14px">For account-related or technical issues, opening a support ticket lets you track your request and get faster help.</p>
                <a href="{{ route("client.tickets.create") }}" class="btn btn-primary" style="width:100%;justify-content:center">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    Open a Ticket
                </a>
            </div>
        </div>
        <div class="pn-card">
            <div class="pn-card-body">
                <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px">Knowledge Base</div>
                <p class="text-muted text-sm" style="margin-bottom:14px">You may find an immediate answer in our knowledge base.</p>
                <a href="{{ route("client.kb.index") }}" class="btn btn-outline" style="width:100%;justify-content:center">Browse Articles</a>
            </div>
        </div>
    </div>
</div>

@endsection
