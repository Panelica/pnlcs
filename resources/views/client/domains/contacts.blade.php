@extends('client.layouts.app')
@section('title', __('client.domains.contacts_title').' - '.$domain->domain)
@section('content')
<div class="pn-page-header">
    <div>
        <h1>{{ __('client.domains.contacts_title') }}</h1>
        <p class="text-muted text-sm">{{ $domain->domain }}</p>
    </div>
    <a href="{{ route('client.domains.show', $domain) }}" class="btn btn-default">{{ __('common.actions.back') }}</a>
</div>

<div class="pn-card">
    <div class="pn-card-body">
        <p class="text-muted text-sm" style="margin-top:0;">{{ __('client.domains.contacts_hint') }}</p>
        @if($fromProfile)
        <p class="text-sm" style="color:var(--warning);">{{ __('client.domains.contacts_from_profile') }}</p>
        @endif
        <form method="POST" action="{{ route('client.domains.contacts.update', $domain) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                @foreach(['first_name', 'last_name', 'company_name', 'email', 'phone', 'address1', 'city', 'state', 'postcode'] as $field)
                <div class="form-group">
                    <label class="form-label" for="c-{{ $field }}">{{ __('common.form.'.($field === 'address1' ? 'address' : $field)) }}</label>
                    <input id="c-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" class="form-control" value="{{ old($field, $contact[$field] ?? '') }}" @if(! in_array($field, ['company_name', 'state'], true)) required @endif>
                    @error($field)<div class="text-sm" style="color:var(--danger);margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                @endforeach
                <div class="form-group">
                    <label class="form-label" for="c-country">{{ __('common.form.country') }}</label>
                    <select id="c-country" name="country" class="form-control" required>
                        <option value="">{{ __('common.form.select_country') }}</option>
                        @foreach($countries as $code => $name)
                        <option value="{{ $code }}" @selected(old('country', $contact['country'] ?? '') === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('country')<div class="text-sm" style="color:var(--danger);margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">{{ __('common.actions.save') }}</button>
        </form>
    </div>
</div>
@endsection
