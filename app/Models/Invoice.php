<?php
namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model {
    use HasFactory;

    protected $fillable = ['client_id', 'invoice_num', 'date', 'due_date', 'date_paid', 'subtotal', 'credit', 'tax', 'tax2', 'total', 'tax_rate', 'tax_rate2', 'status', 'type', 'source_invoice_id', 'reminder_stage', 'reminder_sent_at', 'payment_method', 'pay_method_id', 'notes',
        // Buyer as it stood when the invoice was issued (issue #7). The money
        // was already frozen; these keep the document itself immutable.
        'buyer_first_name', 'buyer_last_name', 'buyer_company_name', 'buyer_email',
        'buyer_address1', 'buyer_address2', 'buyer_city', 'buyer_state',
        'buyer_postcode', 'buyer_country', 'buyer_tax_id', 'buyer_tax_exempt',
        'buyer_custom_fields'];

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

    /**
     * Hide proformas that have been settled: once paid, a proforma is replaced
     * by its VAT invoice and should no longer show in the invoice lists.
     */
    public function scopeExcludeSettledProformas($query)
    {
        return $query->whereNot(function ($q) {
            $q->where('type', 'proforma')->where('status', InvoiceStatus::Paid->value);
        });
    }
    protected function casts(): array { return ['date' => 'date', 'due_date' => 'date', 'date_paid' => 'datetime', 'subtotal' => 'decimal:2', 'credit' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'buyer_custom_fields' => 'array']; }

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

    /** The proforma this VAT invoice was issued from. */
    public function sourceInvoice() { return $this->belongsTo(self::class, 'source_invoice_id'); }

    public function scopeUnpaid($q) { return $q->where('status', InvoiceStatus::Unpaid->value); }
    public function scopeOverdue($q) { return $q->where('status', InvoiceStatus::Overdue->value); }

    /**
     * The debts a client can be suspended over.
     *
     * One question, asked from one place: auto-suspend acts on these, and
     * unsuspend-on-payment leaves a service alone while one of them stands.
     * When the two asked it differently, a client behind on something that was
     * not the service - a domain renewal, a one-off charge - had that service
     * suspended each morning and switched back on half an hour later, with an
     * email each way.
     */
    public function scopeOverduePastGrace($q, int $graceDays)
    {
        return $q->where('status', InvoiceStatus::Overdue->value)
            ->where('due_date', '<', now()->subDays($graceDays));
    }
    public function scopePaid($q) { return $q->where('status', InvoiceStatus::Paid->value); }

    /**
     * The buyer fields to copy off a client when an invoice is issued.
     *
     * @return array<string,mixed>
     */
    public static function buyerSnapshotFrom(Client $client): array
    {
        return [
            'buyer_first_name' => $client->first_name,
            'buyer_last_name' => $client->last_name,
            'buyer_company_name' => $client->company_name,
            'buyer_email' => $client->email,
            'buyer_address1' => $client->address1,
            'buyer_address2' => $client->address2,
            'buyer_city' => $client->city,
            'buyer_state' => $client->state,
            'buyer_postcode' => $client->postcode,
            'buyer_country' => $client->country,
            'buyer_tax_id' => $client->tax_id,
            'buyer_tax_exempt' => (bool) $client->tax_exempt,
            'buyer_custom_fields' => CustomField::invoiceSnapshot($client),
        ];
    }

    /**
     * Buyer field as it should be shown on this invoice: the snapshot taken at
     * issue time, falling back to the live client record for invoices issued
     * before snapshots existed. Nothing was backfilled, so those keep rendering
     * exactly as they do today.
     */
    public function buyer(string $field): mixed
    {
        $snapshot = $this->getAttribute('buyer_' . $field);
        if ($snapshot !== null && $snapshot !== '') {
            return $snapshot;
        }

        return $this->client?->{$field};
    }

    /**
     * The buyer's custom fields as shown on this invoice: the snapshot taken
     * at issue time, lying flat in the same shape the snapshot builder returns
     * (field name => value). Invoices issued before custom-field snapshots
     * existed fall back to the live client's values, exactly like buyer() does.
     *
     * @return array<string, mixed>
     */
    public function buyerCustomFields(): array
    {
        $snapshot = $this->getAttribute('buyer_custom_fields');

        if (is_array($snapshot) && $snapshot !== []) {
            return $snapshot;
        }

        return CustomField::invoiceSnapshot($this->client);
    }

}
