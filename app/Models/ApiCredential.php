<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiCredential extends Model {
    use HasFactory;
    protected $table = "api_credentials";
    protected $fillable = ["admin_id", "api_role_id", "identifier", "secret", "description", "allowed_ips", "active"];
    protected $casts = ["allowed_ips" => "array", "active" => "boolean"];

    /** SHA-256 digest used to store and compare API secrets (secrets are high-entropy,
     *  so a fast digest is safe and lets us look up Bearer tokens by hash). */
    public static function hashSecret(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function admin() { return $this->belongsTo(Admin::class); }
}
