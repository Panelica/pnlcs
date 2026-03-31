<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Affiliate extends Model {
    use HasFactory;

    protected $table = "affiliates";
    protected $fillable = ["client_id", "visitors", "pay_type", "pay_amount", "onetime", "balance", "withdrawn"];

    public function client() { return $this->belongsTo(Client::class); }
}
