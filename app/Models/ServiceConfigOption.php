<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceConfigOption extends Model
{
    protected $fillable = ['service_id', 'config_id', 'option_id', 'qty', 'unit_price'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_price' => 'decimal:2'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ConfigOption::class, 'config_id');
    }

    public function sub(): BelongsTo
    {
        return $this->belongsTo(ConfigOptionSub::class, 'option_id');
    }

    /** What the customer chose, ready for display: "RAM: 4 GB" or "Extra IP x2". */
    public function label(): string
    {
        $name = $this->option?->option_name ?? '';
        $value = $this->sub?->option_name ?? '';

        if ($value === '') {
            return $this->qty > 1 ? "{$name} x{$this->qty}" : $name;
        }

        return $this->qty > 1 ? "{$name}: {$value} x{$this->qty}" : "{$name}: {$value}";
    }

    public function total(): float
    {
        return round((float) $this->unit_price * max(1, (int) $this->qty), 2);
    }
}
