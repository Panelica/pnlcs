<?php

namespace Modules\Addons\BankPayments\Services\Parsers;

use Modules\Addons\BankPayments\Services\BankMessageParser;

/**
 * Parser for Nest Bank notification mail.
 *
 * Nest Bank sends incoming transfers as a signed HTML message with a sentence
 * rather than labelled fields:
 *
 *   dnia 27.11.2026 nastąpił wpływ 1 234,87 PLN na konto BIZnest Konto ...,
 *   od Przykładowy Kontrahent Sp. z o.o., tytułem PRO-2026/11-000123.
 *
 * The labelled-field parser is kept as a fallback for other Nest Bank
 * notification formats. Messages that do not describe an incoming transfer,
 * such as BLIK payment notices, return no transaction.
 */
class NestBankParser implements BankMessageParser
{
    public function isRelevant(string $raw, array $message): bool
    {
        $subject = mb_strtolower((string) ($message['subject'] ?? ''));
        $text = mb_strtolower((string) ($message['body_text'] ?? ''));

        return str_contains($subject, 'wpływ na konto')
            || str_contains($subject, 'wplyw na konto')
            || str_contains($text, 'nastąpił wpływ')
            || str_contains($text, 'nastapil wplyw')
            || (str_contains($text, 'kwota:') && str_contains($text, 'nadawca:'));
    }

    public function parse(string $raw, array $message): array
    {
        $text = (string) ($message['body_text'] ?? '');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        $incoming = $this->incomingTransfer($text, $message);
        if ($incoming !== null) {
            return [$incoming];
        }

        $amountText = $this->field($text, ['Kwota', 'Kwota przelewu', 'Amount']);
        $amount = $this->amount($amountText);

        if ($amount === null || $amount <= 0) {
            return [];
        }

        $currency = $this->currency($amountText)
            ?: $this->field($text, ['Waluta', 'Currency'])
            ?: 'PLN';

        $date = $this->date($this->field($text, ['Data operacji', 'Data', 'Date']));

        $sender = $this->field($text, ['Nadawca', 'Nazwa nadawcy', 'Zleceniodawca', 'Sender']);
        $senderAccount = $this->account($this->field($text, [
            'Rachunek nadawcy', 'Numer rachunku nadawcy', 'Sender account',
        ]));
        $recipientAccount = $this->account($this->field($text, [
            'Rachunek odbiorcy', 'Numer rachunku odbiorcy', 'Recipient account',
        ]));

        $reference = $this->field($text, [
            'Tytuł', 'Tytuł przelewu', 'Tytuł operacji', 'Opis', 'Reference', 'Title',
        ]);

        return [[
            'operation_type' => $this->field($text, ['Typ operacji', 'Operation type']),
            'transaction_date' => $date,
            'amount' => $amount,
            'currency' => strtoupper(substr($currency, 0, 3)),
            'sender_name' => $sender,
            'sender_account' => $senderAccount,
            'recipient_account' => $recipientAccount,
            'reference' => $reference,
            'raw_payload' => [
                'parser' => 'nest_bank',
                'amount_text' => $amountText,
                'subject' => $message['subject'] ?? null,
                'from_email' => $message['from_email'] ?? null,
            ],
        ]];
    }

    /**
     * Parse the sentence used by the real Nest Bank incoming-transfer mail.
     *
     * @return array<string, mixed>|null
     */
    private function incomingTransfer(string $text, array $message): ?array
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', $text));

        $pattern = '/dnia\s+(\d{2}[.\/-]\d{2}[.\/-]\d{4})\s+nastąpił\s+wpływ\s+'
            .'([0-9][0-9\s\x{00a0}.,]*)\s+(PLN|EUR|USD|GBP|CHF)\s+na\s+(?:konto|rachunek)\s+'
            .'(.+?)\s*,\s*od\s+(.+?)\s*,\s*tytułem\s+(.+?)(?:\.\s|\.$|$)/isu';

        if (! preg_match($pattern, $flat, $match)) {
            return null;
        }

        $amount = $this->amount($match[2]);
        if ($amount === null) {
            return null;
        }

        return [
            'operation_type' => 'Wpływ na konto',
            'transaction_date' => $this->date($match[1]),
            'amount' => $amount,
            'currency' => strtoupper($match[3]),
            'sender_name' => $this->clean($match[5]),
            'sender_account' => null,
            'recipient_account' => $this->account($match[4]),
            'reference' => $this->clean($match[6]),
            'raw_payload' => [
                'parser' => 'nest_bank',
                'format' => 'incoming_transfer',
                'amount_text' => $match[2],
                'recipient_account_text' => $this->clean($match[4]),
                'subject' => $message['subject'] ?? null,
                'from_email' => $message['from_email'] ?? null,
            ],
        ];
    }

    private function clean(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value !== '' ? $value : null;
    }

    /**
     * Read a labelled line. The value may not cross into the next label.
     */
    private function field(string $text, array $labels): ?string
    {
        $quoted = array_map(fn (string $label): string => preg_quote($label, '/'), $labels);

        if (preg_match('/^\s*(?:'.implode('|', $quoted).')\s*:\s*(.+)$/miu', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Parse the amount without assuming the thousands/decimal separator.
     */
    private function amount(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! preg_match('/([0-9][0-9\s\x{00a0}.,]*)/u', $value, $match)) {
            return null;
        }

        $number = preg_replace('/[\s\x{00a0}]/u', '', $match[1]) ?? '';

        if (str_contains($number, ',') && str_contains($number, '.')) {
            $lastComma = strrpos($number, ',');
            $lastDot = strrpos($number, '.');

            if ($lastComma > $lastDot) {
                $number = str_replace('.', '', $number);
                $number = str_replace(',', '.', $number);
            } else {
                $number = str_replace(',', '', $number);
            }
        } else {
            $number = str_replace(',', '.', $number);
        }

        $amount = (float) $number;

        return $amount > 0 ? round($amount, 2) : null;
    }

    private function currency(?string $value): ?string
    {
        if ($value && preg_match('/\b(PLN|EUR|USD|GBP|CHF)\b/u', strtoupper($value), $match)) {
            return $match[1];
        }

        return null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // A bank might include a time after the date; use the leading date.
        if (preg_match('/^(\d{2}[.\/-]\d{2}[.\/-]\d{4})/', $value, $match)) {
            $value = $match[1];
        } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match)) {
            $value = $match[1];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date ? $date->format('Y-m-d') : null;
        }

        foreach (['!d.m.Y', '!d-m-Y', '!d/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date === false) {
                continue;
            }

            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        return null;
    }

    private function account(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $value);

        return $normalized !== '' ? strtoupper($normalized) : null;
    }
}
