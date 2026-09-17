@extends("client.layouts.app")
@section("title", __("client.payment_methods.add_card_title"))
@section("content")

{{-- The card never touches this server. The gateway's own script holds the
     number, the browser sends it straight to them, and what comes back here is
     an id. The key printed into the page below is the publishable one, which is
     what it is for; the secret key is read only by the module, server side. --}}

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.payment_methods.add_card_title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.payment_methods.add_card_subtitle') }}</p>
    </div>
    <div>
        <a href="{{ route('client.payment-methods.index') }}" class="btn btn-outline btn-sm">{{ __('client.actions.back') }}</a>
    </div>
</div>

{{-- Stripe is the only gateway this page knows how to draw, and it is the
     only one that implements the vaulting capability. A second one would add
     its own block here the way the invoice page does, rather than being handed
     a card field wired to nothing. --}}
@if($publishableKey && $clientSecret && $gateway === 'stripe')
<div class="pn-card" style="max-width:640px">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.payment_methods.card_details') }}</span></div>
    <div class="pn-card-body">
        <div id="pn-card-element" style="border:1.5px solid var(--border);padding:12px;border-radius:var(--radius-sm);background:var(--bg)"></div>
        <div id="pn-card-error" class="text-sm" style="color:var(--danger,#dc2626);margin-top:8px;min-height:18px"></div>

        {{-- The terms the card is being stored under, on the screen where it is
             stored. Stripe's rule for charging a card with the customer away is
             that the agreement names what will be charged, when, and how to
             stop it; all three are here rather than in a policy page nobody
             opens. --}}
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-top:16px">
            <p class="text-muted text-sm" style="margin:0 0 8px">{{ __('client.payment_methods.consent_terms') }}</p>
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" id="pn-card-consent" style="margin-top:3px">
                <span>{{ __('client.payment_methods.consent_label', ['company' => company_name()]) }}</span>
            </label>
        </div>

        <button type="button" id="pn-card-submit" class="btn btn-primary" style="margin-top:16px" disabled>
            {{ __('client.payment_methods.store_card_button') }}
        </button>

        {{-- A real form, posted by the script once the gateway has finished.
             It carries the session id and the answer to the consent box, and
             it carries the CSRF token as a field rather than a header so that
             it does not depend on anything else being on the page. --}}
        <form method="POST" action="{{ route('client.payment-methods.store-card') }}" id="pn-card-form" style="display:none">
            @csrf
            <input type="hidden" name="session_id" id="pn-card-session">
            <input type="hidden" name="consent" id="pn-card-consent-value" value="0">
        </form>
    </div>
</div>
@else
<div class="pn-alert pn-alert-warning">{{ __('client.payment_methods.card_unavailable') }}</div>
@endif

@endsection

@section("scripts")
@if($publishableKey && $clientSecret && $gateway === 'stripe')
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
    var stripe = Stripe(@json($publishableKey));
    var elements = stripe.elements();
    var card = elements.create("card", { hidePostalCode: false });
    card.mount("#pn-card-element");

    var button = document.getElementById("pn-card-submit");
    var consent = document.getElementById("pn-card-consent");
    var errorBox = document.getElementById("pn-card-error");
    var form = document.getElementById("pn-card-form");
    var busy = false;

    // Nothing is sent to the gateway until the box is ticked. The button is
    // disabled rather than the tick being checked on submit, so that there is
    // no moment where a card is handed over by somebody who has not agreed.
    function sync() { button.disabled = busy || !consent.checked; }
    consent.addEventListener("change", sync);
    card.addEventListener("change", function (e) {
        errorBox.textContent = e.error ? e.error.message : "";
    });

    button.addEventListener("click", function () {
        if (!consent.checked || busy) { return; }

        busy = true;
        sync();
        errorBox.textContent = "";
        button.textContent = @json(__('client.payment_methods.storing'));

        // confirmCardSetup carries out 3-D Secure itself when the bank asks
        // for it, in a modal, and resolves once it is finished either way.
        stripe.confirmCardSetup(@json($clientSecret), { payment_method: { card: card } })
            .then(function (result) {
                if (result.error) {
                    errorBox.textContent = result.error.message || @json(__('client.payment_methods.card_not_stored'));
                    busy = false;
                    button.textContent = @json(__('client.payment_methods.store_card_button'));
                    sync();
                    return;
                }

                // The id, and only the id. The server reads the session back
                // from the gateway and believes that, not this.
                document.getElementById("pn-card-session").value = result.setupIntent.id;
                document.getElementById("pn-card-consent-value").value = "1";
                form.submit();
            })
            .catch(function () {
                errorBox.textContent = @json(__('client.payment_methods.card_not_stored'));
                busy = false;
                button.textContent = @json(__('client.payment_methods.store_card_button'));
                sync();
            });
    });
})();
</script>
@endif
@endsection
