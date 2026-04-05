<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $table = "calendar_events";

    protected $fillable = ["title", "description", "start", "end", "recurring", "admin"];

    protected function casts(): array
    {
        return [
            "start" => "datetime",
            "end" => "datetime",
            "recurring" => "boolean",
        ];
    }
}
