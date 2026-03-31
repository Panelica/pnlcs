<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServerGroup extends Model {
    protected $fillable = ["name", "fill_type"];
    public function servers() { return $this->belongsToMany(Server::class, "server_group_server"); }
}
