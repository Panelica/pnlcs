<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removing a stored card has two halves, and the second one is not free.
 *
 * Soft-deleting the row stops PNLCS charging it — StripeModule refuses a
 * trashed row outright and AutoChargeService never loads one — but the token
 * itself is still attached to the customer at the gateway. A customer who
 * clicks Remove is not asking for a row to be hidden; they are asking for
 * their card not to be kept. Honouring the second half means one HTTP request
 * to the gateway, and that request must not be made inside the click: a
 * gateway that is slow, rate-limiting or down would hang or fail a customer's
 * own action, and a failure there would leave the card looking removed while
 * it is still stored.
 *
 * So the click records the intent and the scheduler carries it out.
 * detach_requested_at is set the moment the customer removes a card;
 * pnlcs:detach-payment-methods calls the gateway, and detached_at is written
 * only when the gateway has confirmed. A row where the first is set and the
 * second is not is work outstanding, retried on every tick until it succeeds,
 * and reported to the operator once it has been outstanding for a day.
 *
 * Two columns rather than one flag, because "never asked" and "asked and done"
 * have to stay distinguishable: clearing the request on success would leave a
 * finished detach looking exactly like a card nobody ever removed, and nothing
 * could then say whether the token at the gateway is gone.
 *
 * NOTHING CHANGES ON AN INSTALLATION THAT HAS NEVER VAULTED A CARD. The only
 * rows this is ever written on are ones with a gateway token, and the only
 * thing that writes a token is the vaulting flow behind AutoChargeEnabled. The
 * bank-account references PNLCS stores today have no remote_token, and their
 * removal is left exactly as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->timestamp('detach_requested_at')->nullable()->after('status');
            $table->timestamp('detached_at')->nullable()->after('detach_requested_at');

            // The sweep asks one question — which rows are waiting? — and asks
            // it every five minutes for the life of the installation. Without
            // this it is a full scan of a table that only grows.
            $table->index(['detach_requested_at', 'detached_at'], 'payment_methods_detach_pending_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex('payment_methods_detach_pending_index');
            $table->dropColumn(['detach_requested_at', 'detached_at']);
        });
    }
};
