<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model {
    protected $table = "affiliates";
    protected $fillable = ["client_id", "visitors", "pay_type", "pay_amount", "onetime", "balance", "withdrawn"];

    public function client() { return $this->belongsTo(Client::class); }
}
