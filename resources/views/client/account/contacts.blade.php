@extends('client.layouts.app')
@section('title', 'Contacts')
@section('content')

<div class="page-header">
    <h1>Contacts</h1>
    <a href="#" class="btn btn-primary btn-sm" onclick="document.getElementById('addContactForm').style.display=document.getElementById('addContactForm').style.display==='none'?'block':'none'; return false;">+ Add Contact</a>
</div>

<div id="addContactForm" style="display:none; margin-bottom:16px;">
    <div class="card">
        <div class="card-header">Add New Contact</div>
        <div class="card-body">
            <form method="POST" action="{{ route('client.account.contacts.store') }}">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">First Name <span style="color:#c43c35;">*</span></label>
                        <input type="text" name="first_name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span style="color:#c43c35;">*</span></label>
                        <input type="text" name="last_name" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span style="color:#c43c35;">*</span></label>
                    <input type="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save Contact</button>
                <button type="button" class="btn btn-default btn-sm" onclick="document.getElementById('addContactForm').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts ?? [] as $contact)
                <tr>
                    <td style="font-weight:500;">{{ $contact->first_name }} {{ $contact->last_name }}</td>
                    <td style="color:#555;">{{ $contact->email }}</td>
                    <td style="color:#777;">{{ $contact->phone ?? '-' }}</td>
                    <td style="font-size:12px; color:#777;">{{ $contact->permissions ?? 'General' }}</td>
                    <td>
                        <a href="#" class="btn btn-default btn-xs">Edit</a>
                        <form method="POST" action="{{ route('client.account.contacts.destroy', $contact) }}" style="display:inline;" onsubmit="return confirm('Remove this contact?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:32px; color:#999;">No contacts added yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
