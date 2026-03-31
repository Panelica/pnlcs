<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model {
    protected $fillable = ["client_id", "invoice_num", "date", "due_date", "date_paid", "subtotal", "credit", "tax", "tax2", "total", "tax_rate", "tax_rate2", "status", "payment_method", "pay_method_id", "notes"];
    protected function casts(): array { return ["date" => "date", "due_date" => "date", "date_paid" => "datetime", "subtotal" => "decimal:2", "credit" => "decimal:2", "tax" => "decimal:2", "total" => "decimal:2"]; }

    public function client() { return $this->belongsTo(Client::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }

    public function scopeUnpaid($q) { return $q->where("status", "unpaid"); }
    public function scopeOverdue($q) { return $q->where("status", "overdue"); }
    public function scopePaid($q) { return $q->where("status", "paid"); }
}
