<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How the customer rated a closed ticket (1-5) and what they said.
 * One per ticket. The Support reports "Ticket Feedback Scores" and
 * "Ticket Ratings" read it.
 */
class TicketFeedback extends Model
{
    protected $table = 'ticket_feedback';

    protected $fillable = ['ticket_id', 'admin_id', 'rating', 'comments'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
