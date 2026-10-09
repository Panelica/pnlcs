{{-- Cookie consent bar (App\Support\Tracking): shown until the visitor chooses; window.pnlcsConsent.open()
     or any [data-pnlcs-consent] element opens it again. Both choices are equally easy, as consent rules ask. --}}
<div id="pnlcs-consent" role="dialog" aria-live="polite" aria-label="{{ __('client.consent.label') }}" hidden>
<p>{{ __('client.consent.text') }}@if($policy) <a href="{{ $policy }}">{{ __('client.consent.more') }}</a>@endif</p>
<div class="b"><button type="button" data-v="necessary">{{ __('client.consent.reject') }}</button><button type="button" data-v="all">{{ __('client.consent.accept') }}</button></div>
</div>
<style>
#pnlcs-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483000;max-width:520px;background:#fff;color:#151414;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.14);padding:16px;font:14px/1.5 system-ui,-apple-system,sans-serif}
#pnlcs-consent p{margin:0 0 12px}#pnlcs-consent a{color:inherit;text-decoration:underline}
#pnlcs-consent .b{display:flex;gap:8px;flex-wrap:wrap}
#pnlcs-consent button{flex:1 1 160px;font:inherit;font-weight:600;padding:9px 14px;border-radius:8px;border:1px solid #151414;cursor:pointer;background:#fff;color:#151414}
#pnlcs-consent button[data-v=all]{background:#151414;color:#fff}
</style>
<script>
(function () {
  var box = document.getElementById('pnlcs-consent'), name = @json($cookie);
  function chosen() { var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=(all|necessary)')); return m ? m[1] : null; }
  function choose(v) {
    document.cookie = name + '=' + v + '; path=/; max-age=15552000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    var g = v === 'all' ? 'granted' : 'denied';
    if (window.gtag) gtag('consent', 'update', {ad_storage: g, ad_user_data: g, ad_personalization: g, analytics_storage: g});
    (window.dataLayer = window.dataLayer || []).push({event: 'pnlcs_consent', consent: v});
    box.hidden = true;
  }
  box.querySelectorAll('button[data-v]').forEach(function (b) { b.addEventListener('click', function () { choose(b.getAttribute('data-v')); }); });
  window.pnlcsConsent = { open: function () { box.hidden = false; }, value: chosen };
  document.addEventListener('click', function (e) { var t = e.target.closest && e.target.closest('[data-pnlcs-consent]'); if (t) { e.preventDefault(); box.hidden = false; } });
  if (!chosen()) box.hidden = false;
})();
</script>
