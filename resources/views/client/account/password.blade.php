@extends("client.layouts.app")
@section("title", __("client.account.change_password"))
@section("styles")
<style>
.pw-strength{height:5px;border-radius:999px;background:var(--border);margin-top:8px;overflow:hidden}
.pw-strength-bar{height:100%;border-radius:999px;transition:width 0.3s,background 0.3s;width:0}
.pw-hint{font-size:11.5px;margin-top:6px;font-weight:600}
</style>
@endsection
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Change Password</h1>
        <p class="pn-page-subtitle">Keep your account secure with a strong password.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start"><div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">Update Password</span></div>
    <div class="pn-card-body">
        @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route("client.account.password.update") }}">
            @csrf
            @method("PUT")
            <div class="form-group">
                <label class="form-label" for="current_password">{{ __('common.form.current_password') }}<span class="req">*</span></label>
                <input type="password" id="current_password" name="current_password" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">{{ __('common.form.new_password') }}<span class="req">*</span></label>
                <input type="password" id="password" name="password" required class="form-control" oninput="checkStrength(this.value)" placeholder="Minimum 8 characters">
                <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                <div class="pw-hint text-muted" id="pwHint">Enter a new password</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirm New Password <span class="req">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

</div>
<div>
<div class="pn-card">
<div class="pn-card-header"><span class="pn-card-title">Password Tips</span></div>
<div class="pn-card-body">
<ul style="list-style:none;padding:0;margin:0;font-size:13px;color:var(--muted);display:flex;flex-direction:column;gap:10px;">
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">1</span> Use at least 12 characters</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">2</span> Mix uppercase, lowercase, numbers &amp; symbols</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">3</span> Avoid dictionary words or personal info</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">4</span> Use a unique password for each site</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">5</span> Consider using a password manager</li>
</ul>
</div>
</div>
<div class="pn-card" style="margin-top:16px">
<div class="pn-card-body" style="text-align:center;padding:20px">
<div style="font-size:32px;margin-bottom:8px">&#128274;</div>
<div style="font-size:13px;color:var(--muted)">Your password was last changed<br><strong style="color:var(--text)">Never</strong></div>
</div>
</div>
</div>
</div>
@section("scripts")
<script>
function checkStrength(v) {
    var bar = document.getElementById("pwBar"), hint = document.getElementById("pwHint");
    var score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var levels = [
        {w:"0%",c:"#ef4444",t:"Too short"},
        {w:"20%",c:"#ef4444",t:"Very weak"},
        {w:"40%",c:"#f59e0b",t:"Weak"},
        {w:"60%",c:"#f59e0b",t:"Fair"},
        {w:"80%",c:"#06d6a0",t:"Strong"},
        {w:"100%",c:"#10b981",t:"Very strong"}
    ];
    var l = levels[Math.min(score, 5)];
    bar.style.width = l.w; bar.style.background = l.c;
    hint.textContent = l.t; hint.style.color = l.c;
}
</script>
@endsection

@endsection
