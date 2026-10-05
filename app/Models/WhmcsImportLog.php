<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhmcsImportLog extends Model
{
    protected $fillable = [
        'source', 'source_table', 'total', 'added', 'updated', 'skipped', 'errors',
        'error_details', 'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'error_details' => 'array',
            'total' => 'integer',
            'added' => 'integer',
            'updated' => 'integer',
            'skipped' => 'integer',
            'errors' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
