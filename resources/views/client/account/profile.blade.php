@extends("client.layouts.app")
@section("title", "Edit Profile")
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Edit Profile</h1>
        <p class="pn-page-subtitle">Update your personal and contact information.</p>
    </div>
</div>

<div class="pn-card" style="max-width:680px">
    <div class="pn-card-header"><span class="pn-card-title">Personal Information</span></div>
    <div class="pn-card-body">
        @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route("client.account.update") }}">
            @csrf
            @method("PUT")
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span class="req">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old("first_name", $user->first_name) }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name <span class="req">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old("last_name", $user->last_name) }}" required class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                <input type="email" id="email" name="email" value="{{ old("email", $user->email) }}" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_name">Company Name</label>
                <input type="text" id="company_name" name="company_name" value="{{ old("company_name", $user->company_name) }}" class="form-control">
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="phone_number">Phone Number</label>
                    <input type="text" id="phone_number" name="phone_number" value="{{ old("phone_number", $user->phone_number) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">Country</label>
                    <select id="country" name="country" class="form-control">
                        <option value="">-- Select Country --</option>
                        @if(isset($countries))
                            @foreach($countries as $code => $name)
                            <option value="{{ $code }}" {{ old("country", $user->country) == $code ? "selected" : "" }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="address1">Street Address</label>
                <input type="text" id="address1" name="address1" value="{{ old("address1", $user->address1) }}" class="form-control" placeholder="Street address, P.O. box, etc.">
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City</label>
                    <input type="text" id="city" name="city" value="{{ old("city", $user->city) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="postcode">Postcode / ZIP</label>
                    <input type="text" id="postcode" name="postcode" value="{{ old("postcode", $user->postcode) }}" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

@endsection
