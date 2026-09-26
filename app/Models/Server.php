<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Server extends Model {
    use HasFactory;

    protected $fillable = ["name", "hostname", "ip_address", "max_accounts", "type", "username", "password", "access_hash", "port", "active", "disabled", "nameserver1", "nameserver2", "nameserver3", "nameserver4", "nameserver5", "settings"];
    protected $hidden = ["password", "access_hash"];
    protected function casts(): array { return ["active" => "boolean", "disabled" => "boolean", "password" => "encrypted", "access_hash" => "encrypted", "settings" => "array"]; }

    /** One of the type-specific settings, or the default when it is not set. */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = ($this->settings ?? [])[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function groups() { return $this->belongsToMany(ServerGroup::class, "server_group_server"); }
}
