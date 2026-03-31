<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClientFile extends Model {
    protected $table = "client_files";
    protected $fillable = ["client_id", "title", "filename", "admin_only"];

    public function client() { return $this->belongsTo(Client::class); }
}
