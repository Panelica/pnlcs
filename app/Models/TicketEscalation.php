<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketEscalation extends Model
{
    protected $fillable = [
        "name", "departments", "statuses", "priorities",
        "time_elapsed", "new_department_id", "new_priority",
        "flag_to", "notify", "add_reply",
    ];

    protected function casts(): array
    {
        return [
            "departments" => "array",
            "statuses" => "array",
            "priorities" => "array",
            "notify" => "boolean",
        ];
    }

    public function newDepartment()
    {
        return $this->belongsTo(TicketDepartment::class, "new_department_id");
    }

    public function flagAdmin()
    {
        return $this->belongsTo(Admin::class, "flag_to");
    }
}
