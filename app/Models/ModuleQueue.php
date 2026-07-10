<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleQueue extends Model
{
    protected $table = 'module_queue';

    protected $fillable = [
        'service_id', 'action', 'status', 'attempts', 'max_attempts',
        'next_attempt_at', 'last_error', 'payload', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'         => 'array',
            'next_attempt_at' => 'datetime',
            'completed_at'    => 'datetime',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            });
    }
}
