<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model {
    protected $fillable = ["type", "rel_id", "field_name", "field_type", "description", "field_options", "regex", "admin_only", "required", "sort_order", "show_on_invoice", "show_on_order"];
    protected function casts(): array { return ["admin_only" => "boolean", "required" => "boolean", "show_on_invoice" => "boolean", "show_on_order" => "boolean"]; }
    public function values() { return $this->hasMany(CustomFieldValue::class, "field_id"); }
}
