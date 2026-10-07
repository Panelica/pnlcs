<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhmcsImportProfile extends Model
{
    protected $fillable = [
        'connection_id', 'name', 'source_table', 'target', 'mapping', 'constants', 'transforms', 'match_key', 'import_mode',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'constants' => 'array',
            'transforms' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhmcsImportConnection::class, 'connection_id');
    }
}
