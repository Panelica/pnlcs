<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Service extends Model {
    protected $fillable = ["client_id", "order_id", "product_id", "server_id", "domain", "payment_method", "qty", "first_payment_amount", "amount", "billing_cycle", "next_due_date", "registration_date", "status", "username", "password", "disk_usage", "disk_limit", "bw_usage", "bw_limit", "suspension_date", "suspension_reason", "termination_date", "notes"];
    protected $hidden = ["password"];
    protected function casts(): array { return ["next_due_date" => "date", "registration_date" => "date", "suspension_date" => "date", "termination_date" => "date", "amount" => "decimal:2", "first_payment_amount" => "decimal:2", "password" => "encrypted"]; }

    public function client() { return $this->belongsTo(Client::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function server() { return $this->belongsTo(Server::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function addons() { return $this->hasMany(ServiceAddon::class); }

    public function scopeActive($q) { return $q->where("status", "active"); }
}
