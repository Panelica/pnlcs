<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TicketDepartment extends Model {
    protected $fillable = ["name", "description", "email", "clients_only", "hidden", "sort_order", "feedback_request"];
    protected function casts(): array { return ["clients_only" => "boolean", "hidden" => "boolean", "feedback_request" => "boolean"]; }
    public function tickets() { return $this->hasMany(Ticket::class, "department_id"); }
}
