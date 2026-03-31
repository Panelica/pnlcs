<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;

class TransactionService
{
    public function recordPayment(Invoice $invoice, array $data): Transaction
    {
        return Transaction::create([
            'client_id' => $invoice->client_id,
            'currency_id' => $data['currency_id'] ?? null,
            'gateway' => $data['gateway'] ?? 'manual',
            'date' => now(),
            'description' => $data['description'] ?? "Payment for Invoice #{$invoice->invoice_num}",
            'amount_in' => $data['amount'] ?? $invoice->total,
            'fees' => $data['fees'] ?? 0,
            'amount_out' => 0,
            'rate' => 1,
            'transaction_id' => $data['transaction_id'] ?? null,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function recordRefund(Invoice $invoice, float $amount, string $reason = ''): Transaction
    {
        return Transaction::create([
            'client_id' => $invoice->client_id,
            'gateway' => 'manual',
            'date' => now(),
            'description' => "Refund for Invoice #{$invoice->invoice_num}" . ($reason ? " - {$reason}" : ''),
            'amount_in' => 0,
            'amount_out' => $amount,
            'rate' => 1,
            'invoice_id' => $invoice->id,
        ]);
    }
}
