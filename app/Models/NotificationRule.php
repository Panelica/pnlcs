<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    protected $fillable = ["event", "provider_id", "conditions", "active"];

    protected function casts(): array
    {
        return [
            "conditions" => "array",
            "active" => "boolean",
        ];
    }

    public function provider()
    {
        return $this->belongsTo(NotificationProvider::class, "provider_id");
    }
}
