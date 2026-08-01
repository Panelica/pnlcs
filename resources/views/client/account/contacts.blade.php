@extends('client.layouts.app')
@section('title', __('client.contacts.title'))
@section('content')

<div class="page-header">
    <h1>{{ __('client.contacts.title') }}</h1>
    <a href="#" class="btn btn-primary btn-sm" onclick="document.getElementById('addContactForm').style.display=document.getElementById('addContactForm').style.display==='none'?'block':'none'; return false;">{{ __('client.contacts.add_contact') }}</a>
</div>

<div id="addContactForm" style="display:none; margin-bottom:16px;">
    <div class="pn-card">
        <div class="pn-card-header">{{ __('client.contacts.add_new_contact') }}</div>
        <div class="pn-card-body">
            <form method="POST" action="{{ route('client.account.contacts.store') }}">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.first_name') }}<span style="color:#c43c35;">*</span></label>
                        <input type="text" name="first_name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.form.last_name') }}<span style="color:#c43c35;">*</span></label>
                        <input type="text" name="last_name" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.form.email') }}<span style="color:#c43c35;">*</span></label>
                    <input type="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.form.phone') }}</label>
                    <input type="text" name="phone_number" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('client.contacts.save_contact') }}</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('addContactForm').style.display='none'">{{ __('common.actions.cancel') }}</button>
            </form>
        </div>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body" style="padding:0;">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.name') }}</th>
                    <th>{{ __('common.table.email') }}</th>
                    <th>{{ __('common.form.phone') }}</th>
                    <th>{{ __('client.contacts.receives') }}</th>
                    <th>{{ __('common.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts ?? [] as $contact)
                <tr>
                    <td style="font-weight:500;">{{ $contact->first_name }} {{ $contact->last_name }}</td>
                    <td style="color:#555;">{{ $contact->email }}</td>
                    <td style="color:#777;">{{ $contact->phone_number ?: '-' }}</td>
                    <td style="font-size:12px; color:#777;">
                        @php
                            $kinds = collect([
                                'general_emails' => __('client.contacts.kind_general'),
                                'invoice_emails' => __('client.contacts.kind_invoice'),
                                'product_emails' => __('client.contacts.kind_product'),
                                'domain_emails' => __('client.contacts.kind_domain'),
                                'support_emails' => __('client.contacts.kind_support'),
                            ])->filter(fn ($label, $field) => $contact->{$field})->values();
                        @endphp
                        {{ $kinds->isEmpty() ? __('client.contacts.receives_nothing') : $kinds->implode(', ') }}
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline btn-xs" onclick="openModal('edit-contact-{{ $contact->id }}')">{{ __('common.actions.edit') }}</button>
                        <form method="POST" action="{{ route('client.account.contacts.destroy', $contact) }}" style="display:inline;" onsubmit="return confirm('{{ __("client.contacts.confirm_remove") }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.remove') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:32px; color:#999;">{{ __('client.contacts.no_contacts') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@foreach($contacts as $contact)
<x-modal name="edit-contact-{{ $contact->id }}" title="{{ __('client.contacts.edit_contact') }}" maxWidth="md">
    <form method="POST" action="{{ route('client.account.contacts.update', $contact) }}">
        @csrf @method('PUT')
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">{{ __('common.form.first_name') }}</label><input type="text" name="first_name" value="{{ $contact->first_name }}" required class="form-control"></div>
            <div class="form-group"><label class="form-label">{{ __('common.form.last_name') }}</label><input type="text" name="last_name" value="{{ $contact->last_name }}" required class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">{{ __('common.form.email_address') }}</label><input type="email" name="email" value="{{ $contact->email }}" required class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('common.form.company_name') }}</label><input type="text" name="company_name" value="{{ $contact->company_name }}" class="form-control"></div>
        <div class="form-group"><label class="form-label">{{ __('common.form.phone_number') }}</label><input type="text" name="phone_number" value="{{ $contact->phone_number }}" class="form-control"></div>
        <div class="form-group">
            <label class="form-label">{{ __('client.contacts.receives') }}</label>
            @foreach(['general_emails' => 'kind_general', 'invoice_emails' => 'kind_invoice', 'product_emails' => 'kind_product', 'domain_emails' => 'kind_domain', 'support_emails' => 'kind_support'] as $field => $label)
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;">
                <input type="checkbox" name="{{ $field }}" value="1" @checked($contact->{$field})>
                {{ __('client.contacts.'.$label) }}
            </label>
            @endforeach
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <button type="button" onclick="closeModal('edit-contact-{{ $contact->id }}')" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save_changes') }}</button>
        </div>
    </form>
</x-modal>
@endforeach

@endsection