<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model {
    protected $fillable = ["title", "announcement", "published"];
    protected function casts(): array { return ["published" => "boolean"]; }
}
