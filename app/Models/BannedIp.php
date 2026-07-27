<?php

namespace App\Models;

use App\Http\Middleware\BlockBannedIp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BannedIp extends Model
{
    use HasFactory;

    protected $table = 'banned_ips';

    protected $fillable = ['ip', 'reason'];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(BlockBannedIp::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }
}
