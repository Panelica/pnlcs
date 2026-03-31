<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketFeedback extends Model {
    protected $table = "ticket_feedback";
    protected $fillable = ["ticket_id", "admin_id", "rating", "comments"];

    public function ticket() { return $this->belongsTo(Ticket::class); }
}
