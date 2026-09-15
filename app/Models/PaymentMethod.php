<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected $fillable = ["client_id", "description", "contact_id", "gateway_name", "payment_type", "last_four", "expiry_date", "remote_token", "is_default", "gateway_customer_id", "card_brand", "exp_month", "exp_year", "status"];
    protected $hidden = ["remote_token"];
    protected function casts(): array { return ["is_default" => "boolean", "exp_month" => "integer", "exp_year" => "integer"]; }
    public function client() { return $this->belongsTo(Client::class); }
}
