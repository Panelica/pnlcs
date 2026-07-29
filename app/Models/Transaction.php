<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model {
    use HasFactory;

    protected $fillable = ["client_id", "currency_id", "gateway", "date", "description", "amount_in", "fees", "amount_out", "rate", "transaction_id", "invoice_id", "refund_id"];

    /**
     * Ledger rows that are not trade with a customer.
     *
     * Commission owed to an affiliate and the payout that settles it move
     * through the same table as customer payments, but they are what the
     * business pays out, not what it takes in.
     *
     * @var array<int, string>
     */
    public const NON_REVENUE_GATEWAYS = ['affiliate_commission', 'affiliate_payout'];

    /** Money taken from, or handed back to, customers. */
    public function scopeRevenue($query)
    {
        return $query->whereNotIn('gateway', self::NON_REVENUE_GATEWAYS);
    }
    protected function casts(): array { return ["date" => "date", "amount_in" => "decimal:2", "fees" => "decimal:2", "amount_out" => "decimal:2"]; }

    public function client() { return $this->belongsTo(Client::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
