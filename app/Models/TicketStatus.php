<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model {
    protected $fillable = ["title", "color", "sort_order", "show_active", "show_awaiting", "auto_close"];
    protected function casts(): array { return ["show_active" => "boolean", "show_awaiting" => "boolean"]; }
}
