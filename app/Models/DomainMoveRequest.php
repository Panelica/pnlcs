<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainMoveRequest extends Model
{
    public const DAYS = 7;

    protected $fillable = ['domain_id', 'from_client_id', 'to_client_id', 'status', 'expires_at', 'decided_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function fromClient()
    {
        return $this->belongsTo(Client::class, 'from_client_id');
    }

    public function toClient()
    {
        return $this->belongsTo(Client::class, 'to_client_id');
    }

    /** Waiting for the other account, and not past its date. */
    public function scopeOpen($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
