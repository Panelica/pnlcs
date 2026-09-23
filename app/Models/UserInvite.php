<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An invitation for a login to join a customer account. The token column
 * holds a hash of the link's token, never the token itself.
 */
class UserInvite extends Model
{
    use SoftDeletes;

    /** How long an invitation link stays good. */
    public const VALID_DAYS = 7;

    protected $fillable = ['token', 'email', 'client_id', 'invited_by', 'permissions', 'accepted_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'accepted_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function findByPlainToken(string $plain): ?self
    {
        return static::where('token', hash('sha256', $plain))->first();
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->created_at->copy()->addDays(self::VALID_DAYS)->isFuture();
    }
}
