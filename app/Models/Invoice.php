<?php
namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model {
    use HasFactory;

    protected $fillable = ['client_id', 'invoice_num', 'date', 'due_date', 'date_paid', 'subtotal', 'credit', 'tax', 'tax2', 'total', 'tax_rate', 'tax_rate2', 'status', 'reminder_stage', 'reminder_sent_at', 'payment_method', 'pay_method_id', 'notes'];

    /**
     * Invoices the customer still owes money on.
     *
     * Overdue and part-paid ones count: an invoice does not stop being
     * outstanding because it went past its due date.
     */
    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'overdue', 'partially_paid']);
    }
    protected function casts(): array { return ['date' => 'date', 'due_date' => 'date', 'date_paid' => 'datetime', 'subtotal' => 'decimal:2', 'credit' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2']; }

    public function client() { return $this->belongsTo(Client::class); }
    /**
     * What is still owed on this invoice.
     *
     * r131-due: the invoice page shows this to the customer as the remaining
     * balance, and every pay-now path used to ask the gateway for the total
     * instead - so somebody who had paid half by bank transfer was shown 60
     * left and their card was charged 100.
     */
    public function amountDue(): float
    {
        return max(0.0, app(\App\Services\PaymentService::class)->balance($this));
    }

    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }

    public function scopeUnpaid($q) { return $q->where('status', InvoiceStatus::Unpaid->value); }
    public function scopeOverdue($q) { return $q->where('status', InvoiceStatus::Overdue->value); }
    public function scopePaid($q) { return $q->where('status', InvoiceStatus::Paid->value); }
}
