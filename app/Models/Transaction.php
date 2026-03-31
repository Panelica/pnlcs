<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $fillable = ["client_id", "currency_id", "gateway", "date", "description", "amount_in", "fees", "amount_out", "rate", "transaction_id", "invoice_id", "refund_id"];
    protected function casts(): array { return ["date" => "date", "amount_in" => "decimal:2", "fees" => "decimal:2", "amount_out" => "decimal:2"]; }

    public function client() { return $this->belongsTo(Client::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
