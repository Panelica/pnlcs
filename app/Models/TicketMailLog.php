<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMailLog extends Model
{
    protected $table = 'ticket_mail_logs';

    protected $fillable = ['date', 'to', 'name', 'email', 'subject', 'message', 'status'];

    protected function casts(): array
    {
        return ['date' => 'datetime'];
    }
}
