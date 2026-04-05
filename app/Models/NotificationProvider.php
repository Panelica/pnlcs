<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationProvider extends Model
{
    protected $fillable = ["name", "type", "settings", "active"];

    protected function casts(): array
    {
        return [
            "settings" => "array",
            "active" => "boolean",
        ];
    }

    public function rules()
    {
        return $this->hasMany(NotificationRule::class, "provider_id");
    }
}
