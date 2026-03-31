<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketStatus extends Model {
    use HasFactory;

    protected $fillable = ["title", "color", "sort_order", "show_active", "show_awaiting", "auto_close"];
    protected function casts(): array { return ["show_active" => "boolean", "show_awaiting" => "boolean"]; }
}
