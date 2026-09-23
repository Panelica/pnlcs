<?php
namespace App\Models;
use App\Services\Module\ModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class PaymentMethod extends Model {
    use SoftDeletes;

    /** A stored card the gateway will still accept. */
    public const STATUS_ACTIVE = "active";

    /** Still stored, but the gateway has stopped accepting it. */
    public const STATUS_REQUIRES_UPDATE = "requires_update";

    /**
     * A method is usable until something says otherwise.
     *
     * The column defaults to 'active' in the database, which covered every row
     * written before it existed but left a freshly made model carrying null
     * until it was read back. Anything that refuses to charge a method that is
     * not active would then refuse a method nobody had faulted, so the default
     * is stated here as well as in the schema.
     */
    protected $attributes = ["status" => self::STATUS_ACTIVE];

    protected $fillable = ["client_id", "description", "contact_id", "gateway_name", "payment_type", "last_four", "expiry_date", "remote_token", "is_default", "gateway_customer_id", "card_brand", "exp_month", "exp_year", "status", "detach_requested_at", "detached_at"];
    protected $hidden = ["remote_token"];
    protected function casts(): array { return ["is_default" => "boolean", "exp_month" => "integer", "exp_year" => "integer", "detach_requested_at" => "datetime", "detached_at" => "datetime"]; }
    public function client() { return $this->belongsTo(Client::class); }

    /**
     * Is this a method the gateway holds a token for?
     *
     * The bank-account references PNLCS has always stored are rows with a
     * description and four digits and nothing behind them. A vaulted card is a
     * row with a token that will be accepted by a gateway until somebody
     * detaches it, which is a different kind of object with a different kind of
     * obligation attached to it.
     */
    public function isVaulted(): bool
    {
        return is_string($this->remote_token) && $this->remote_token !== "";
    }

    /**
     * Stop using this method and ask the gateway to forget it.
     *
     * The row goes at once - that is what stops PNLCS charging it - and the
     * gateway half is recorded for pnlcs:detach-payment-methods to carry out
     * (see requestGatewayDetach). The client area and the API both remove a
     * method through here, so neither can forget the second half.
     */
    public function remove(): void
    {
        $this->requestGatewayDetach();
        $this->delete();
    }

    /** Make this the customer's default method, and no other. */
    public function makeDefault(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            static::where('client_id', $this->client_id)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * The customer has asked for this card not to be kept.
     *
     * Written in the customer's own request, where it costs one UPDATE and
     * cannot fail on a gateway being slow; the sweep that calls the gateway
     * runs on the scheduler. Recorded only for a row the gateway actually
     * holds something for — there is nothing to detach for a bank-account
     * reference, and writing the column on one would have the sweep ask a
     * gateway about a card that was never stored with it.
     *
     * AND ONLY WHERE SOMETHING IN THIS INSTALLATION COULD CARRY IT OUT.
     * isVaulted() is a weaker test than it reads as: it is satisfied by any
     * non-empty remote_token, and a token is written by paths that have nothing
     * to do with this feature — GatewayWebhookController::rememberIyzicoCard
     * stores iyzico's card handle on an ordinary payment where the customer
     * ticked "save my card", with no setting consulted and the charger switched
     * off. IyzicoModule implements GatewayModuleInterface only, so no code in
     * this product can detach that token, ever. A request recorded against it
     * could never be satisfied: the sweep would count it outstanding, log an
     * error about it, and come back five minutes later, for the life of the
     * installation — an unbounded set of rows nothing can resolve, on a shop
     * that never switched automatic charging on. That is a behaviour change
     * with the feature off, which is the one thing this feature promised not to
     * be.
     *
     * So the test is whether a registered module for this gateway implements
     * the detaching interface. A capability rather than a configuration:
     * ModuleRegistry::canDetachStoredMethods carries the reasoning for why the
     * gateway being switched on this minute is the wrong question.
     *
     * A card this installation cannot detach is said out loud once, here, where
     * there is a customer and a moment to attach it to — rather than every five
     * minutes for ever by a sweep that can do nothing about it. The customer's
     * side of the promise is kept either way: the row is deleted and PNLCS
     * stops using the card. The half that needs hands is the gateway's own
     * dashboard, and this is what tells somebody to go there.
     *
     * Idempotent: a second removal of the same row (or a row already swept)
     * leaves the first request's moment alone, so the age this is reported by
     * stays the age of the customer's actual request.
     */
    public function requestGatewayDetach(): void
    {
        if (! $this->isVaulted() || $this->detach_requested_at !== null || $this->detached_at !== null) {
            return;
        }

        if (! app(ModuleRegistry::class)->canDetachStoredMethods((string) $this->gateway_name)) {
            Log::error("A customer removed a stored card that this installation cannot detach at the gateway", [
                "method" => $this->id,
                "client" => $this->client_id,
                "gateway" => $this->gateway_name,
                "action" => "PNLCS has stopped using the card. Remove the stored token in the gateway's own dashboard.",
            ]);

            return;
        }

        $this->forceFill(["detach_requested_at" => now()])->save();
    }

    /** Rows the customer has removed that the gateway has not yet let go of. */
    public function scopeAwaitingGatewayDetach($query)
    {
        return $query->withTrashed()
            ->whereNotNull("remote_token")
            ->whereNotNull("detach_requested_at")
            ->whereNull("detached_at");
    }

    /**
     * The gateway is no longer holding this card. Stop asking about it.
     *
     * Also true when the gateway says it has never heard of the token: there is
     * nothing left to detach, and a sweep that kept trying would ask about that
     * one row for ever.
     */
    public function markGatewayDetached(): void
    {
        $this->forceFill(["detached_at" => now()])->save();
    }

    /**
     * A stored card with nothing else claiming the default becomes it.
     *
     * The charger will not guess between two cards — AutoChargeService refuses
     * an invoice when a client has several and none is marked — so a client
     * whose cards are all unmarked has a feature that collects nothing and says
     * so only in a log. The first card a client stores is not a guess: it is
     * the only answer there is.
     *
     * IT NEVER DEMOTES A CHOICE SOMEBODY MADE. If any live method is already
     * flagged — another card, or the bank account they picked — this does
     * nothing, because moving the flag would be overruling the customer about
     * which of their own methods is used, and doing it silently from inside a
     * webhook. A client in that position with two cards still gets the
     * charger's refusal and one click on this page settles it.
     *
     * Called from the vaulting path only. Bank-account references have never
     * had a default set for them on creation and still do not.
     */
    public function becomeDefaultIfClientHasNone(): void
    {
        if ($this->is_default) {
            return;
        }

        $taken = static::query()
            ->where("client_id", $this->client_id)
            ->where("id", "!=", $this->id)
            ->where("is_default", true)
            ->exists();

        if ($taken) {
            return;
        }

        $this->forceFill(["is_default" => true])->save();
    }
}
