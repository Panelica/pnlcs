<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

/**
 * Paying several open invoices in one go (WHMCS calls it Mass Pay).
 *
 * A customer with five open invoices had to pay five times. This raises one
 * payment invoice with a line per invoice (its balance) and, once that is
 * paid, settles each one through the existing path: the money becomes
 * account credit (like Add Funds) and InvoiceService::applyCredit() pays the
 * invoices in turn, so each fires its own InvoicePaid - renewals,
 * provisioning, receipts - exactly as if it had been paid on its own.
 * Whatever is left (an invoice paid elsewhere meanwhile) stays as credit.
 *
 * The payment invoice is not a tax document: it has its own type and number
 * series ("masspay", PAY-n), so the VAT series has no gap, a proforma scheme
 * does not turn it into a VAT invoice, and KSeF does not send it. Automatic
 * card charging, late fees and reminders leave it alone - the invoices it
 * pays already go through them.
 */
class MassPaymentService
{
    public const TYPE = 'masspay';

    /** The invoice_items.type of a line that pays another invoice. */
    public const ITEM_TYPE = 'Invoice';

    public const NUMBER_FORMAT = 'PAY-{num}';

    /**
     * @param  list<int>  $invoiceIds
     * @return array{invoice: ?Invoice, message: ?string}
     */
    public function create(Client $client, array $invoiceIds): array
    {
        $payments = app(PaymentService::class);

        $targets = Invoice::where('client_id', $client->id)
            ->whereIn('id', array_map('intval', $invoiceIds))
            ->outstanding()
            ->where('type', '!=', self::TYPE)
            ->whereDoesntHave('items', fn ($q) => $q->whereIn('type', ['AddFunds', self::ITEM_TYPE]))
            ->orderBy('due_date')->orderBy('id')
            ->get()
            ->filter(fn (Invoice $i) => $payments->balance($i) > 0.009)
            ->values();

        if ($targets->count() < 2) {
            return ['invoice' => null, 'message' => __('client.invoices.mass_pay_pick_two')];
        }

        $invoice = DB::transaction(function () use ($client, $targets, $payments) {
            // One payment invoice at a time: an older one nobody paid is
            // withdrawn rather than left to pile up.
            Invoice::where('client_id', $client->id)->where('type', self::TYPE)
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])
                ->update(['status' => InvoiceStatus::Cancelled->value]);

            $total = round($targets->sum(fn (Invoice $i) => $payments->balance($i)), 2);
            $numbers = app(InvoiceService::class);

            $invoice = Invoice::create([
                ...Invoice::buyerSnapshotFrom($client),
                'client_id' => $client->id,
                'type' => self::TYPE,
                'invoice_num' => $numbers->renderInvoiceNumber(self::NUMBER_FORMAT, $numbers->nextInvoiceSequence(self::NUMBER_FORMAT, self::TYPE)),
                'date' => today(),
                'due_date' => today(),
                'subtotal' => $total,
                'credit' => 0,
                'tax' => 0,
                'tax2' => 0,
                'total' => $total,
                'tax_rate' => 0,
                'tax_rate2' => 0,
                'status' => InvoiceStatus::Unpaid->value,
            ]);

            foreach ($targets as $target) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'type' => self::ITEM_TYPE,
                    'rel_id' => $target->id,
                    'description' => __('client.invoices.mass_pay_line', ['num' => $target->invoice_num ?: $target->id]),
                    'amount' => $payments->balance($target),
                    'taxed' => false,
                ]);
            }

            return $invoice;
        });

        ActivityLog::log('Payment invoice #'.$invoice->invoice_num.' raised for '.$targets->count().' invoices', auth()->user()?->email, $client->id);

        return ['invoice' => $invoice, 'message' => null];
    }

    public static function isMassPayment(Invoice $invoice): bool
    {
        return ($invoice->type ?? null) === self::TYPE
            || $invoice->items()->where('type', self::ITEM_TYPE)->exists();
    }

    /** Called once the payment invoice is paid: settles each invoice it lists from the credit it became. */
    public function settle(Invoice $paid): void
    {
        $credits = app(InvoiceService::class);

        foreach ($paid->items()->where('type', self::ITEM_TYPE)->orderBy('id')->get() as $line) {
            $target = Invoice::where('client_id', $paid->client_id)->find($line->rel_id);
            if ($target) {
                $credits->applyCredit($target, (float) $line->amount);
            }
        }
    }
}
