<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['tid', 'department_id', 'client_id', 'contact_id', 'name', 'email', 'cc', 'title', 'message', 'status', 'priority', 'admin', 'attachment', 'last_reply', 'flag', 'escalated_at', 'service', 'merged_ticket_id', 'editor'];

    /**
     * Somebody has replied: the clock starts again.
     *
     * Clearing escalated_at is what lets a later silence escalate the ticket
     * a second time. The escalation service does not use this — its own
     * auto-reply must not wipe the mark it just made, or the rule re-fires
     * every cycle and mails the customer forever.
     */
    public function recordReply(string $status): void
    {
        $this->update([
            'status' => $status,
            'last_reply' => now(),
            'escalated_at' => null,
        ]);
    }

    protected function casts(): array
    {
        return ['last_reply' => 'datetime', 'escalated_at' => 'datetime'];
    }

    public function department()
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function notes()
    {
        return $this->hasMany(TicketNote::class);
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }
}
