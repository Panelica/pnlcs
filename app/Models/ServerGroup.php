<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServerGroup extends Model {
    use HasFactory;

    protected $fillable = ["name", "fill_type"];
    public function servers() { return $this->belongsToMany(Server::class, "server_group_server"); }
}
