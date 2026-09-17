<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which client a half-finished vaulting session belongs to.
 *
 * WHY THIS IS NOT READ BACK OUT OF THE GATEWAY. StripeModule can ask Stripe
 * whose SetupIntent an id names, because it wrote metadata[client_id] onto the
 * intent when it opened it, and TokenizableGatewayInterface makes refusing
 * somebody else's session a requirement rather than a nicety: without it a
 * customer who submits another account's session id has that account's stored
 * payment method written against their own, and then pays their invoices with
 * it.
 *
 * PayPal's vault has no metadata field. It has an optional
 * customer.merchant_customer_id which could carry the same fact, but where that
 * object belongs in a POST /v3/vault/setup-tokens body is not shown in any
 * sample the PayPal module was written against, and a field in the wrong place
 * is either rejected — breaking every attempt to store a payment method — or
 * ignored, which is worse: the check would read an absent value, compare it to
 * nothing, and quietly stop being a check at all.
 *
 * So the binding is kept here instead, written before the session id ever
 * reaches a browser. It does not depend on a third party echoing anything back,
 * it cannot be defeated by guessing a PayPal id, and it is the same answer for
 * any future gateway whose vault has nowhere to hang our own identifiers.
 *
 * One row per session. The unique key is on (gateway, session_id) rather than
 * on the client, because a client may quite reasonably open the form twice and
 * only finish one of them; what must never happen is one session id resolving
 * to two clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_vault_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // The gateway's own id for the unfinished session (PayPal: the
            // setup token id).
            $table->string('session_id', 191);
            $table->timestamps();

            $table->unique(['gateway', 'session_id']);
            $table->index(['gateway', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_vault_sessions');
    }
};
