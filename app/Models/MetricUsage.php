<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricUsage extends Model
{
    protected $table = "metric_usage";

    protected $fillable = [
        "service_id",
        "metric_type",
        "value",
        "recorded_at",
    ];

    protected function casts(): array
    {
        return [
            "value" => "decimal:2",
            "recorded_at" => "datetime",
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
