<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model {
    protected $fillable = ["service_id", "type", "reason"];
    public function service() { return $this->belongsTo(Service::class); }
}
