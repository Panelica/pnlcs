{{-- The Google reCAPTCHA challenge for one guarded form ($form is a key of
     RecaptchaService::FORMS). Rendered only when that form's switch is on and
     both keys are saved; the controller asks the same question, so the page
     never shows a challenge it will not check, nor checks one it did not show. --}}
@php
    $recaptcha = app(\App\Services\RecaptchaService::class);
@endphp
@if($recaptcha->enabled($form))
<div class="form-group">
    <div class="g-recaptcha" data-sitekey="{{ $recaptcha->siteKey() }}"></div>
</div>
<script src="https://www.google.com/recaptcha/api.js?hl={{ urlencode(app()->getLocale()) }}" async defer></script>
@endif
