@extends('client.layouts.app')
@section('title', 'Change Password')
@section('content')

<div class="page-header">
    <h1>Change Password</h1>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-header">Update Password</div>
    <div class="card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.account.password.update') }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label" for="current_password">Current Password <span style="color:#c43c35;">*</span></label>
                <input type="password" id="current_password" name="current_password" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">New Password <span style="color:#c43c35;">*</span></label>
                <input type="password" id="password" name="password" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirm New Password <span style="color:#c43c35;">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

@endsection
