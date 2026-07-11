<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiCredential extends Model {
    use HasFactory;
    protected $table = "api_credentials";
    protected $fillable = ["admin_id", "api_role_id", "identifier", "secret", "description", "allowed_ips", "active"];
    protected $casts = ["allowed_ips" => "array", "active" => "boolean"];

    public function admin() { return $this->belongsTo(Admin::class); }
}
