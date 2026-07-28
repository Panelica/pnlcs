<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'type', 'reason'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Requests arrive with two vocabularies: the customer form posts
     * "Immediate" / "End of Billing Period" while the API defaults to
     * "end_of_billing". Both mean the same thing, so normalise before use.
     */
    public function isImmediate(): bool
    {
        $type = strtolower(str_replace([' ', '-'], '_', (string) $this->type));

        return $type === 'immediate';
    }
}
