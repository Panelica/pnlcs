<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model {
    protected $fillable = ["tid", "department_id", "client_id", "contact_id", "name", "email", "cc", "title", "message", "status", "priority", "admin", "attachment", "last_reply", "flag", "service", "merged_ticket_id", "editor"];
    protected function casts(): array { return ["last_reply" => "datetime"]; }

    public function department() { return $this->belongsTo(TicketDepartment::class, "department_id"); }
    public function client() { return $this->belongsTo(Client::class); }
    public function replies() { return $this->hasMany(TicketReply::class); }
    public function notes() { return $this->hasMany(TicketNote::class); }

    public function scopeOpen($q) { return $q->where("status", "open"); }
}
