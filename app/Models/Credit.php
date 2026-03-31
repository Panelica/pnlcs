<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model {
    protected $fillable = ["client_id", "admin_id", "date", "description", "amount"];
    protected function casts(): array { return ["date" => "date", "amount" => "decimal:2"]; }
    public function client() { return $this->belongsTo(Client::class); }
}
