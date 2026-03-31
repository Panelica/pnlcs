<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketEscalation extends Model {
    protected $table = "ticket_escalations";
    protected $fillable = ["name", "departments", "statuses", "priorities", "time_elapsed", "new_department_id", "new_priority", "flag_to", "notify", "add_reply"];

}
