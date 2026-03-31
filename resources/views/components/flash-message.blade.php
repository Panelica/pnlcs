@if(session('success'))
<div style="background:#dff0d8; border:1px solid #d6e9c6; color:#3c763d; padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between;">
    <span>{{ session('success') }}</span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#3c763d; font-size:16px; cursor:pointer; padding:0; line-height:1; opacity:0.7;">&times;</button>
</div>
@endif

@if(session('error'))
<div style="background:#f2dede; border:1px solid #ebccd1; color:#a94442; padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between;">
    <span>{{ session('error') }}</span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#a94442; font-size:16px; cursor:pointer; padding:0; line-height:1; opacity:0.7;">&times;</button>
</div>
@endif

@if(session('warning'))
<div style="background:#fcf8e3; border:1px solid #faebcc; color:#8a6d3b; padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between;">
    <span>{{ session('warning') }}</span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#8a6d3b; font-size:16px; cursor:pointer; padding:0; line-height:1; opacity:0.7;">&times;</button>
</div>
@endif

@if(session('info'))
<div style="background:#d9edf7; border:1px solid #bce8f1; color:#31708f; padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between;">
    <span>{{ session('info') }}</span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#31708f; font-size:16px; cursor:pointer; padding:0; line-height:1; opacity:0.7;">&times;</button>
</div>
@endif

@if($errors->any())
<div style="background:#f2dede; border:1px solid #ebccd1; color:#a94442; padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:14px;">
    <strong>Please fix the following errors:</strong>
    <ul style="margin:6px 0 0; padding-left:18px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
