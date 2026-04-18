<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketNote extends Model {
    protected $fillable = ["ticket_id", "admin", "message", "attachments", "editor"];
    public function ticket() { return $this->belongsTo(Ticket::class); }
}
