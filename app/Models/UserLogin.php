<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sign-in to the client area, or one wrong password for an existing login. */
class UserLogin extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'successful', 'method', 'ip_address', 'user_agent', 'device'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
