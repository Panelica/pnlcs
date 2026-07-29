<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannedEmail extends Model
{
    /**
     * Whether the ban list covers this address.
     *
     * An entry is either a whole address or a bare domain — the column is
     * called `domain` and the screen offers both — so a ban on example.com
     * covers everyone on it.
     */
    public static function blocks(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        $domain = strtolower(explode('@', $email)[1] ?? '');

        try {
            return static::where(function ($q) use ($email, $domain) {
                $q->whereRaw('LOWER(domain) = ?', [$email]);

                if ($domain !== '') {
                    $q->orWhereRaw('LOWER(domain) = ?', [$domain])
                        ->orWhereRaw('LOWER(domain) = ?', ['@'.$domain]);
                }
            })->exists();
        } catch (\Throwable) {
            // List unreadable (installer, broken database) — never lock people
            // out of signing up because of it.
            return false;
        }
    }

    use HasFactory;

    protected $table = 'banned_emails';

    protected $fillable = ['domain', 'reason'];
}
