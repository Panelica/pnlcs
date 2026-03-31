<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketReply extends Model {
    protected $fillable = ["ticket_id", "client_id", "contact_id", "message", "admin", "attachment", "rating", "editor"];
    public function ticket() { return $this->belongsTo(Ticket::class); }
}
