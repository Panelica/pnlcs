<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketReply extends Model {
    use HasFactory;

    protected $fillable = ["ticket_id", "client_id", "contact_id", "message", "admin", "attachment", "rating", "editor"];
    public function ticket() { return $this->belongsTo(Ticket::class); }
}
