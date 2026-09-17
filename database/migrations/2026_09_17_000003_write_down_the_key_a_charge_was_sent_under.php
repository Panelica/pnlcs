<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name the gateway knows this charge by, written down before it is sent.
 *
 * A replay is only a replay if it arrives under the same idempotency key as the
 * request it repeats. StripeModule DERIVED that key — hash_hmac over the
 * invoice, the card, the minor amount and the currency, keyed on
 * config('app.key') — and InvoiceChargeAttempt proved four of those five inputs
 * before letting a rescuer send. The fifth was never proved and could move on
 * its own: config/app.php supports APP_PREVIOUS_KEYS, so an operator can rotate
 * APP_KEY the way Laravel documents and leave every encrypted value readable,
 * and app/Casts/EncryptedValue.php hands back a value it cannot decrypt as-is,
 * so on an install whose gateway_settings row predates that cast the Stripe
 * secret survives a rotation with no rotation machinery at all. Either way the
 * gateway goes on authenticating, the row goes on swearing the replay is
 * provably the same request, and the request goes out under a key Stripe has
 * never seen: a brand-new charge on top of one whose outcome was unknown.
 * Measured on the real crontab: two real requests, two distinct keys, fifteen
 * minutes apart, on a charge the gateway had already cached as succeeded.
 *
 * A DERIVED KEY IS ONLY AS STABLE AS ITS LEAST STABLE INPUT, and the list of
 * inputs is open-ended — the application key today, a currency correction, a
 * rounding fix, an input somebody adds next year. A key that is WRITTEN DOWN
 * when the first request goes out and read back on the replay cannot drift for
 * any of those reasons, because nothing recomputes it. That is what this column
 * is.
 *
 * The key is not a secret. It travels to Stripe in a plain header, so the HMAC
 * bought no confidentiality; what a key has to be is STABLE for one operation
 * and UNIQUE across operations. A stored 160-bit random value is both by
 * construction, and it is unique across installations too — better than the
 * digest was, which collided outright for two installs restored from one image,
 * since they share an application key and an invoice numbering.
 *
 * NO BACKFILL, AND THAT IS THE HONEST VALUE. What key an existing in-flight row
 * was sent under cannot be recovered: it depended on the application key AT THE
 * TIME OF THE SEND, which is the very thing that may have moved. So the column
 * stays NULL on rows written before this migration, and
 * InvoiceChargeAttempt::replayIsProvablyTheSameRequest() reads NULL as "we
 * cannot prove it" and parks the row for a person — loudly, through the same
 * shout() every other unprovable replay goes through, and with the operator's
 * release exit (InvoiceController::releaseChargeReview) still open, so the
 * invoice does not stop being collectable either. One review per in-flight row,
 * once, in exchange for never replaying under a key nobody can name. Rows in
 * every other state need nothing: a scheduled retry goes through restart(),
 * which mints a key of its own.
 *
 * NOT UNIQUE, deliberately. 160 bits of randomness needs no index to be unique,
 * and an index would make a collision throw UniqueConstraintViolationException
 * out of claim() — which catches exactly that exception and reads it as "another
 * worker already has this invoice". A guard whose only effect would be to make
 * an impossible event answer the wrong question is worse than no guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_charge_attempts', function (Blueprint $table) {
            // Wide enough for what is sent today — 'pnlcs-offsession-' and forty
            // hex characters, 57 in all — with room for a longer scope name.
            // Stripe accepts up to 255; nothing here goes near it.
            $table->string('idempotency_key', 64)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_charge_attempts', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
