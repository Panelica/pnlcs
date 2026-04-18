<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Server extends Model {
    use HasFactory;

    protected $fillable = ["name", "hostname", "ip_address", "max_accounts", "type", "username", "password", "access_hash", "port", "active", "disabled", "nameserver1", "nameserver2", "nameserver3", "nameserver4", "nameserver5"];
    protected $hidden = ["password", "access_hash"];
    protected function casts(): array { return ["active" => "boolean", "disabled" => "boolean", "password" => "encrypted", "access_hash" => "encrypted"]; }

    public function groups() { return $this->belongsToMany(ServerGroup::class, "server_group_server"); }
}
