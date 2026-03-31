<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CancellationRequest extends Model {
    use HasFactory;

    protected $fillable = ["service_id", "type", "reason"];
    public function service() { return $this->belongsTo(Service::class); }
}
