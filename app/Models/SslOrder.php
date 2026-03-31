<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SslOrder extends Model {
    protected $table = "ssl_orders";
    protected $fillable = ["client_id", "service_id", "remote_id", "module", "cert_type", "config_data", "status", "certificate_expiry_date"];

    public function client() { return $this->belongsTo(Client::class); }
}
