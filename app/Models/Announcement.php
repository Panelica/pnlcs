<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model {
    use HasFactory;

    protected $fillable = ["title", "announcement", "published"];
    protected function casts(): array { return ["published" => "boolean"]; }
}
