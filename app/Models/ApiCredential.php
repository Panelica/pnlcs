<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model {
    protected $table = "api_credentials";
    protected $fillable = ["admin_id", "api_role_id", "identifier", "secret", "description", "allowed_ips", "active"];

    public function admin() { return $this->belongsTo(Admin::class); }
}
