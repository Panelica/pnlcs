@extends('client.layouts.app')
@section('title', 'My Profile')
@section('content')

<div class="page-header">
    <h1>My Profile</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-header">Personal Information</div>
    <div class="card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.account.update') }}">
            @csrf
            @method('PUT')
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span style="color:#c43c35;">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name <span style="color:#c43c35;">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email Address <span style="color:#c43c35;">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_name">Company Name</label>
                <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="form-control">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="phone_number">Phone</label>
                    <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">Country</label>
                    <select id="country" name="country" class="form-control">
                        <option value="">-- Select Country --</option>
                        @if(isset($countries))
                            @foreach($countries as $code => $name)
                            <option value="{{ $code }}" {{ old('country', $user->country) == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="address1">Address</label>
                <input type="text" id="address1" name="address1" value="{{ old('address1', $user->address1) }}" class="form-control" placeholder="Street address">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="city">City</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $user->city) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="postcode">Postcode / ZIP</label>
                    <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $user->postcode) }}" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

@endsection
