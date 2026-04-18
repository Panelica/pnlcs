<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Email extends Model {
    protected $fillable = ["client_id", "subject", "message", "date", "to", "cc", "bcc", "pending", "failed", "failure_reason"];
    protected function casts(): array { return ["date" => "datetime", "pending" => "boolean", "failed" => "boolean"]; }
    public function client() { return $this->belongsTo(Client::class); }
}
