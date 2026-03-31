@extends('admin.layouts.app')
@section('title', 'General Settings')
@section('content')

<div class="page-header">
    <h1>General Settings</h1>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('admin.settings.general.update') }}" style="max-width:700px;">
    @csrf

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Company Information</strong></div>
        <div class="card-body">
            <div class="form-group"><label class="form-label">Company Name</label><input type="text" name="CompanyName" value="{{ $settings['CompanyName'] ?? '' }}" class="form-control"></div>
            <div class="form-group"><label class="form-label">Domain</label><input type="text" name="Domain" value="{{ $settings['Domain'] ?? '' }}" class="form-control" placeholder="yourdomain.com"></div>
            <div class="form-group"><label class="form-label">Company Address</label><textarea name="Address" rows="3" class="form-control">{{ $settings['Address'] ?? '' }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Phone Number</label><input type="text" name="PhoneNumber" value="{{ $settings['PhoneNumber'] ?? '' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Company Email</label><input type="email" name="Email" value="{{ $settings['Email'] ?? '' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>Localization</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Default Language</label><input type="text" name="DefaultLanguage" value="{{ $settings['DefaultLanguage'] ?? 'english' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Default Country</label><input type="text" name="Country" value="{{ $settings['Country'] ?? '' }}" class="form-control" placeholder="US"></div>
                <div class="form-group"><label class="form-label">Date Format</label><input type="text" name="DateFormat" value="{{ $settings['DateFormat'] ?? 'd/m/Y' }}" class="form-control" placeholder="d/m/Y"></div>
                <div class="form-group"><label class="form-label">Timezone</label><input type="text" name="Timezone" value="{{ $settings['Timezone'] ?? 'UTC' }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:15px;">
        <div class="card-header"><strong>System Settings</strong></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label class="form-label">Admin Login URL Prefix</label><input type="text" name="AdminDir" value="{{ $settings['AdminDir'] ?? 'admin' }}" class="form-control"></div>
                <div class="form-group"><label class="form-label">Client Area Template</label><input type="text" name="ActiveClientAreaTemplate" value="{{ $settings['ActiveClientAreaTemplate'] ?? 'default' }}" class="form-control"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:5px;">
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="MaintenanceMode" value="1" {{ !empty($settings['MaintenanceMode']) ? 'checked' : '' }}> Maintenance Mode (client area offline)</label>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="OrderFormDisplayedOn" value="orderforms" {{ ($settings['OrderFormDisplayedOn'] ?? '') === 'orderforms' ? 'checked' : '' }}> Enable Online Order Form</label>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
@endsection
