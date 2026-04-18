<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model {
    use SoftDeletes;
    protected $fillable = ["client_id", "description", "contact_id", "gateway_name", "payment_type", "last_four", "expiry_date", "remote_token"];
    protected $hidden = ["remote_token"];
    public function client() { return $this->belongsTo(Client::class); }
}
