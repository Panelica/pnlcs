<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KsefImportRun extends Model
{
    protected $fillable = [
        'started_at', 'finished_at', 'status', 'date_type', 'from_date',
        'to_date', 'fetched', 'created', 'updated', 'errors', 'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'from_date' => 'datetime',
            'to_date' => 'datetime',
            'fetched' => 'integer',
            'created' => 'integer',
            'updated' => 'integer',
            'errors' => 'integer',
        ];
    }
}
