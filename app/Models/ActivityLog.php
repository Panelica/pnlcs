<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model {
    protected $fillable = ["date", "description", "user", "ip_address"];
    protected function casts(): array { return ["date" => "datetime"]; }

    public static function log(string $description, ?string $user = null): void {
        static::create(["date" => now(), "description" => $description, "user" => $user, "ip_address" => request()->ip()]);
    }
}
