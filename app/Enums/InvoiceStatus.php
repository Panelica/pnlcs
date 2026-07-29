<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Collections = 'collections';
    case PaymentPending = 'payment_pending';

    /**
     * Statuses that mean nothing more is owed on the invoice.
     *
     * Anything else is still open, and an open invoice for a renewal is the
     * reason not to raise a second one.
     *
     * @return array<int, string>
     */
    public static function settled(): array
    {
        return [self::Paid->value, self::Cancelled->value, self::Refunded->value];
    }
}
