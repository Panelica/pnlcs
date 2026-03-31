<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model {
    use HasFactory;

    protected $fillable = ["code", "type", "recurring", "value", "cycles", "applies_to", "requires", "start_date", "expiration_date", "max_uses", "uses", "lifetime_promo", "apply_once", "new_signups_only", "existing_client", "upgrades", "notes"];
    protected function casts(): array { return ["start_date" => "date", "expiration_date" => "date", "recurring" => "boolean", "lifetime_promo" => "boolean", "apply_once" => "boolean", "new_signups_only" => "boolean", "existing_client" => "boolean", "upgrades" => "boolean", "value" => "decimal:2"]; }

    public function isValid(): bool {
        if ($this->max_uses > 0 && $this->uses >= $this->max_uses) return false;
        if ($this->expiration_date && $this->expiration_date->isPast()) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        return true;
    }
}
