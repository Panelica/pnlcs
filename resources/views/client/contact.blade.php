@extends('client.layouts.app')
@section('title', 'Contact Us')
@section('content')

<div class="page-header">
    <h1>Contact Us</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-header">Send us a Message</div>
    <div class="card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.contact.submit') }}">
            @csrf
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="name">Your Name <span style="color:#c43c35;">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', auth()->user()?->full_name) }}" required maxlength="100" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span style="color:#c43c35;">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="200" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="department_id">Department <span style="color:#c43c35;">*</span></label>
                <select id="department_id" name="department_id" required class="form-control">
                    <option value="">-- Select a department --</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="subject">Subject <span style="color:#c43c35;">*</span></label>
                <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="200" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="message">Message <span style="color:#c43c35;">*</span></label>
                <textarea id="message" name="message" rows="6" required maxlength="5000" class="form-control">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</div>

@endsection
