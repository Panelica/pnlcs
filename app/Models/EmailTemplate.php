<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailTemplate extends Model {
    use HasFactory;

    protected $fillable = ["type", "name", "subject", "message", "from_name", "from_email", "disabled", "custom", "language", "copy_to", "plaintext"];
    protected function casts(): array { return ["disabled" => "boolean", "custom" => "boolean", "plaintext" => "boolean"]; }
}
