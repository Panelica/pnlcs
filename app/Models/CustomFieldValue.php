<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model {
    protected $fillable = ["field_id", "rel_id", "value"];
    public function field() { return $this->belongsTo(CustomField::class, "field_id"); }
}
