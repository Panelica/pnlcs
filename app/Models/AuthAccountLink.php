<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A login's account at an outside sign-in provider (auth_account_links, a
 * table that existed from the start and was never used). One row per
 * provider account: (provider, provider_user_id) is unique.
 */
class AuthAccountLink extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
