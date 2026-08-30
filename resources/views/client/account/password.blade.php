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
        <h1 class="pn-page-title">{{ __('client.password.page_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.password.page_subtitle') }}</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start"><div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.password.update_password') }}</span></div>
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
                <div style="display:flex;gap:6px;">
                    <input type="password" id="current_password" name="current_password" required class="form-control">
                    <button type="button" onclick="togglePw('current_password')" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.toggle') }}">&#128065;</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">{{ __('common.form.new_password') }}<span class="req">*</span></label>
                <div style="display:flex;gap:6px;">
                    <input type="password" id="password" name="password" required class="form-control" oninput="checkStrength(this.value)" placeholder="{{ __('client.password.min_chars') }}">
                    <button type="button" onclick="generatePw()" class="btn btn-default" style="white-space:nowrap;flex-shrink:0;" title="{{ __('admin.clients.generate_password') }}">&#128273;</button>
                    <button type="button" onclick="copyPw('password', this)" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.copy') }}">&#128203;</button>
                    <button type="button" onclick="togglePw('password')" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.toggle') }}">&#128065;</button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                <div class="pw-hint text-muted" id="pwHint">{{ __("client.password.enter_new") }}</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">{{ __('client.password.confirm_new') }} <span class="req">*</span></label>
                <div style="display:flex;gap:6px;">
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
                    <button type="button" onclick="togglePw('password_confirmation')" class="btn btn-default" style="flex-shrink:0;" title="{{ __('common.toggle') }}">&#128065;</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('client.password.update_btn') }}</button>
        </form>
    </div>
</div>

</div>
<div>
<div class="pn-card">
<div class="pn-card-header"><span class="pn-card-title">{{ __('client.password.tips_title') }}</span></div>
<div class="pn-card-body">
<ul style="list-style:none;padding:0;margin:0;font-size:13px;color:var(--muted);display:flex;flex-direction:column;gap:10px;">
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">1</span> {{ __('client.password.tip_1') }}</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">2</span> {{ __('client.password.tip_2') }}</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">3</span> {{ __('client.password.tip_3') }}</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">4</span> {{ __('client.password.tip_4') }}</li>
<li style="display:flex;gap:8px;align-items:start"><span style="color:var(--accent);font-weight:700">5</span> {{ __('client.password.tip_5') }}</li>
</ul>
</div>
</div>
<div class="pn-card" style="margin-top:16px">
<div class="pn-card-body" style="text-align:center;padding:20px">
<div style="font-size:32px;margin-bottom:8px">&#128274;</div>
<div style="font-size:13px;color:var(--muted)">{{ __('client.password.last_changed') }}<br><strong style="color:var(--text)">{{ __('client.password.never') }}</strong></div>
</div>
</div>
</div>
</div>
@section("scripts")
<script>
function generatePw() {
    var upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    var lower = "abcdefghijkmnopqrstuvwxyz";
    var digits = "23456789";
    var symbols = "!@#$%^&*_-+=?";
    var all = upper + lower + digits + symbols;
    var out = [
        upper[Math.floor(Math.random() * upper.length)],
        lower[Math.floor(Math.random() * lower.length)],
        digits[Math.floor(Math.random() * digits.length)],
        symbols[Math.floor(Math.random() * symbols.length)],
    ];
    for (var i = 0; i < 12; i++) out.push(all[Math.floor(Math.random() * all.length)]);
    out.sort(function () { return Math.random() - 0.5; });
    var pw = out.join("");
    var a = document.getElementById("password");
    var b = document.getElementById("password_confirmation");
    if (a) { a.value = pw; a.type = "text"; checkStrength(pw); }
    if (b) { b.value = pw; b.type = "text"; }
}

function togglePw(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === "password" ? "text" : "password";
}

function copyPw(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var prev = el.type;
    el.type = "text";
    el.select();
    try { document.execCommand("copy"); } catch (e) {}
    el.type = prev;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).catch(function () {});
    }
    if (btn) {
        var orig = btn.innerHTML;
        btn.innerHTML = "&#10003;";
        setTimeout(function () { btn.innerHTML = orig; }, 1200);
    }
}

function checkStrength(v) {
    var bar = document.getElementById("pwBar"), hint = document.getElementById("pwHint");
    var score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var levels = [
        {w:"0%",c:"#ef4444",t:"{{ __('client.password.too_short') }}"},
        {w:"20%",c:"#ef4444",t:"{{ __('client.password.very_weak') }}"},
        {w:"40%",c:"#f59e0b",t:"{{ __('client.password.weak') }}"},
        {w:"60%",c:"#f59e0b",t:"{{ __('client.password.fair') }}"},
        {w:"80%",c:"#06d6a0",t:"{{ __('client.password.strong') }}"},
        {w:"100%",c:"#10b981",t:"{{ __('client.password.very_strong') }}"}
    ];
    var l = levels[Math.min(score, 5)];
    bar.style.width = l.w; bar.style.background = l.c;
    hint.textContent = l.t; hint.style.color = l.c;
}
</script>
@endsection

@endsection
