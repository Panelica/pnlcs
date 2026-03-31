<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Upgrade extends Model {
    use HasFactory;

    protected $table = "upgrades";
    protected $fillable = ["client_id", "order_id", "type", "rel_id", "original_value", "new_value", "amount", "status"];

    public function client() { return $this->belongsTo(Client::class); }
}
