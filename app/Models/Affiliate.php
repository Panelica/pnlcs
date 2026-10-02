<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $table = 'affiliates';

    protected $fillable = ['client_id', 'code', 'visitors', 'pay_type', 'pay_amount', 'onetime', 'balance', 'withdrawn'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /** What a personal code may look like: 3-32 of a-z, 0-9 and dashes, not only digits (those are ids). */
    public const CODE_RULE = '/^(?![0-9]+$)[a-z0-9](?:[a-z0-9-]{1,30})[a-z0-9]$/';

    /** The link to share: the personal code when there is one, else the id. */
    public function link(): string
    {
        return url('/').'?ref='.($this->code ?: $this->id);
    }

    /** The affiliate a ?ref= value points at: a personal code, or (as before) an id. */
    public static function findByRef(mixed $ref): ?self
    {
        $ref = strtolower(trim((string) $ref));
        if ($ref === '') {
            return null;
        }

        return ctype_digit($ref)
            ? static::find((int) $ref)
            : static::where('code', $ref)->first();
    }
}
