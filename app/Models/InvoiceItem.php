<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model {
    use HasFactory;

    protected $fillable = ["invoice_id", "client_id", "type", "rel_id", "description", "qty", "amount", "taxed", "tax_rate", "tax_label", "due_date"];
    protected function casts(): array { return ["qty" => "integer", "amount" => "decimal:2", "taxed" => "boolean", "tax_rate" => "decimal:2", "due_date" => "date"]; }

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
