<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSpamFilter extends Model
{
    protected $table = "ticket_spam_filters";

    protected $fillable = ["type", "content"];
}
