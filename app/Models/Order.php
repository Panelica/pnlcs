<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $fillable = ["order_num", "client_id", "contact_id", "date", "promo_code", "amount", "payment_method", "invoice_id", "status", "ip_address", "fraud_module", "fraud_output", "notes"];
    protected function casts(): array { return ["date" => "date", "amount" => "decimal:2"]; }

    public function client() { return $this->belongsTo(Client::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function services() { return $this->hasMany(Service::class, "order_id"); }
    public function domains() { return $this->hasMany(Domain::class, "order_id"); }
}
