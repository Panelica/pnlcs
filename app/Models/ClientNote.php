<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClientNote extends Model {
    protected $table = "client_notes";
    protected $fillable = ["client_id", "admin", "note", "sticky"];

    public function client() { return $this->belongsTo(Client::class); }
}
