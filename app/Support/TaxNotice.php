<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Setting;

/**
 * The legal line a shop that charges no tax has to print (#449): Austria's
 * small-business rule, Germany's § 19 UStG and the like. The operator writes
 * it once on the tax page, in the words their law asks for; it is shown on a
 * document that carries no tax, and on nothing that does.
 *
 * It is read when the document is shown, like the company's own address and
 * tax number on the invoice: an invoice issued with tax never gains it, and an
 * invoice issued without tax shows the text the shop has now.
 */
class TaxNotice
{
    public const SETTING = 'TaxExemptionNotice';

    public static function text(): string
    {
        return trim((string) Setting::get(self::SETTING, ''));
    }

    public static function forInvoice(Invoice $invoice): ?string
    {
        $tax = abs((float) $invoice->tax) + abs((float) ($invoice->tax2 ?? 0));

        return $tax < 0.005 ? self::orNull() : null;
    }

    /** @param  array<string, mixed>  $totals  CartService::calculateTotal() */
    public static function forCart(array $totals): ?string
    {
        return (float) ($totals['tax'] ?? 0) < 0.005 ? self::orNull() : null;
    }

    private static function orNull(): ?string
    {
        $text = self::text();

        return $text === '' ? null : $text;
    }
}
