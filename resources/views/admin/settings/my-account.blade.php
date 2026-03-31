@extends("admin.layouts.app")
@section("title", "My Account")
@section("content")

<div class="page-header">
    <h1>My Account</h1>
</div>

@if($errors->any())
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route("admin.my-account.update") }}">
    @csrf

    <div class="card" style="max-width:640px;">
        <div class="card-header">Profile Information</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">First Name <span style="color:#c43c35;">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="{{ old("first_name", $admin->first_name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span style="color:#c43c35;">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="{{ old("last_name", $admin->last_name) }}" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address <span style="color:#c43c35;">*</span></label>
                <input type="email" name="email" class="form-control" value="{{ old("email", $admin->email) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Ticket Signature <small style="color:#999;">(optional, appears at bottom of ticket replies)</small></label>
                <textarea name="signature" class="form-control" rows="4" style="resize:vertical;">{{ old("signature", $admin->signature) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:640px;margin-top:16px;">
        <div class="card-header">Change Password <small style="font-weight:400;color:#999;">(leave blank to keep current password)</small></div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="new_password_confirmation" class="form-control" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="{{ route("admin.dashboard") }}" class="btn btn-default" style="margin-left:8px;">Cancel</a>
    </div>
</form>

@endsection
