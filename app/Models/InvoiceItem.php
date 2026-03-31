<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model {
    protected $fillable = ["invoice_id", "client_id", "type", "rel_id", "description", "amount", "taxed", "due_date"];
    protected function casts(): array { return ["amount" => "decimal:2", "taxed" => "boolean", "due_date" => "date"]; }

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
