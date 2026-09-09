@extends("client.layouts.app")
@section("title", __("client.contact.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.contact.heading') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.contact.subtitle') }}</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:32px;max-width:100%">
    <div class="pn-card">
        <div class="pn-card-header"><span class="pn-card-title">{{ __('client.contact.send_message') }}</span></div>
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
                        <label class="form-label" for="name">{{ __('client.contact.your_name') }} <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old("name", auth()->user()?->full_name) }}" required maxlength="100" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">{{ __('common.form.email_address') }}<span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old("email", auth()->user()?->email) }}" required maxlength="200" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="department_id">{{ __('client.contact.department') }} <span class="req">*</span></label>
                    <select id="department_id" name="department_id" required class="form-control">
                        <option value="">-- {{ __('client.contact.select_department') }} --</option>
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
                    <textarea id="message" name="message" rows="7" required maxlength="5000" class="form-control" placeholder="{{ __('client.contact.how_can_we_help') }}">{{ old("message") }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('client.contact.send_message_btn') }}</button>
            </form>
        </div>
    </div>

    <div>
        {{-- Who the customer is dealing with. E-commerce law in most places
             wants the seller's trading name, address, telephone and tax
             details reachable on the site itself, not only inside the
             contracts. Read from the settings the invoice and the legal
             documents also read, so the three can never disagree. --}}
        @php
            $sellerLines = array_values(array_filter([
                trim((string) \App\Models\Setting::get('CompanyLegalName', '')),
                trim((string) \App\Models\Setting::get('Address', '')),
                trim(implode(' ', array_filter([
                    trim((string) \App\Models\Setting::get('Postcode', '')),
                    trim((string) \App\Models\Setting::get('CompanyCity', '')),
                ]))),
                trim((string) \App\Models\Setting::get('Country', '')),
            ]));
            $sellerPhone = trim((string) \App\Models\Setting::get('PhoneNumber', ''));
            $sellerEmail = trim((string) \App\Models\Setting::get('Email', ''));
            $sellerTaxOffice = trim((string) \App\Models\Setting::get('TaxOffice', ''));
            $sellerTaxId = trim((string) \App\Models\Setting::get('TaxID', ''));
            $sellerRegistry = trim((string) (\App\Models\Setting::get('MersisNo', '') ?: \App\Models\Setting::get('TradeRegistryNo', '')));
        @endphp
        @if($sellerLines || $sellerPhone || $sellerEmail)
        <div class="pn-card mb-16">
            <div class="pn-card-body">
                <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px">{{ __('client.contact.company_details') }}</div>
                <div class="text-muted text-sm" style="line-height:1.75">
                    @foreach($sellerLines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if($sellerPhone)<div style="margin-top:8px"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $sellerPhone) }}">{{ $sellerPhone }}</a></div>@endif
                    @if($sellerEmail)<div><a href="mailto:{{ $sellerEmail }}">{{ $sellerEmail }}</a></div>@endif
                    @if($sellerTaxOffice || $sellerTaxId)
                        <div style="margin-top:8px">
                            @if($sellerTaxOffice){{ __('client.contact.tax_office') }}: {{ $sellerTaxOffice }}@endif
                            @if($sellerTaxOffice && $sellerTaxId) &middot; @endif
                            @if($sellerTaxId){{ __('common.form.tax_id') }}: {{ $sellerTaxId }}@endif
                        </div>
                    @endif
                    @if($sellerRegistry)<div>{{ __('client.contact.registry_no') }}: {{ $sellerRegistry }}</div>@endif
                    <div style="margin-top:10px">
                        <a href="{{ route('legal.index') }}">{{ __('client.contact.legal_documents') }}</a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="pn-card mb-16">
            <div class="pn-card-body">
                <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px">{{ __('client.contact.prefer_tickets') }}</div>
                <p class="text-muted text-sm" style="margin-bottom:14px">{{ __('client.contact.ticket_benefit') }}</p>
                <a href="{{ route("client.tickets.create") }}" class="btn btn-primary" style="width:100%;justify-content:center">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    {{ __('client.nav.open_ticket') }}
                </a>
            </div>
        </div>
        <div class="pn-card">
            <div class="pn-card-body">
                <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px">{{ __('client.nav.knowledge_base') }}</div>
                <p class="text-muted text-sm" style="margin-bottom:14px">{{ __('client.contact.kb_benefit') }}</p>
                <a href="{{ route("client.kb.index") }}" class="btn btn-outline" style="width:100%;justify-content:center">{{ __('client.contact.browse_articles') }}</a>
            </div>
        </div>
    </div>
</div>

@endsection
