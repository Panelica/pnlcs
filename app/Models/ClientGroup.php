<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        "name", "color", "discount_percent",
        "suspend_exempt", "terminate_exempt",
    ];

    protected function casts(): array
    {
        return [
            "discount_percent" => "decimal:2",
            "suspend_exempt" => "boolean",
            "terminate_exempt" => "boolean",
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, "group_id");
    }
}
