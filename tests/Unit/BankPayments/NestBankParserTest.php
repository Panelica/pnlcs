<?php

use App\Services\Mail\RawMessageParser;
use Modules\Addons\BankPayments\Services\Parsers\NestBankParser;

function nestBankFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/BankPayments/nest-bank-example.eml'));
}

function nestBankIncomingFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/BankPayments/nest-bank-incoming-transfer.eml'));
}

it('parses the documented Nest Bank notification fields', function () {
    $raw = (new RawMessageParser)->parse(nestBankFixture());
    $transactions = (new NestBankParser)->parse(nestBankFixture(), $raw);

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]['amount'])->toBe(1234.56)
        ->and($transactions[0]['currency'])->toBe('PLN')
        ->and($transactions[0]['transaction_date'])->toBe('2026-11-26')
        ->and($transactions[0]['operation_type'])->toBe('Przelew przychodzący')
        ->and($transactions[0]['sender_name'])->toBe('Acme Sp. z o.o.')
        ->and($transactions[0]['sender_account'])->toBe('12345678901234567890123456')
        ->and($transactions[0]['recipient_account'])->toBe('98765432109876543210987654')
        ->and($transactions[0]['reference'])->toBe('Faktura R2026/10/NR-161');
});

it('returns no transaction when the amount is absent', function () {
    $raw = "From: bank@example.test\nSubject: Powiadomienie\n\nBank: Nest Bank\nNadawca: Jan Kowalski\n";

    expect((new NestBankParser)->parse($raw, [
        'body_text' => "Bank: Nest Bank\nNadawca: Jan Kowalski\n",
        'subject' => 'Powiadomienie',
    ]))->toBe([]);
});

it('accepts the common decimal formats without losing the amount', function () {
    $parser = new NestBankParser;

    foreach ([
        'Kwota: 1 234,56 PLN' => 1234.56,
        'Kwota: 1234.56 PLN' => 1234.56,
        'Kwota: 1.234,56 PLN' => 1234.56,
        'Kwota: 1,234.56 PLN' => 1234.56,
    ] as $body => $expected) {
        $parsed = $parser->parse('', ['body_text' => "Data operacji: 2026-11-26\n".$body]);

        expect($parsed[0]['amount'])->toBe($expected);
    }
});

it('parses the real signed HTML incoming-transfer format', function () {
    $raw = (new RawMessageParser)->parse(nestBankIncomingFixture());
    $transactions = (new NestBankParser)->parse(nestBankIncomingFixture(), $raw);

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]['operation_type'])->toBe('Wpływ na konto')
        ->and($transactions[0]['amount'])->toBe(1234.87)
        ->and($transactions[0]['currency'])->toBe('PLN')
        ->and($transactions[0]['transaction_date'])->toBe('2026-11-27')
        ->and($transactions[0]['sender_name'])->toBe('Przykładowy Kontrahent Sp. z o.o.')
        ->and($transactions[0]['reference'])->toBe('PRO-2026/11-000123')
        ->and($transactions[0]['recipient_account'])->toBe('BIZNESTKONTO12345678901234567890123456');
});

it('does not treat a BLIK payment notice as an incoming transfer', function () {
    $body = '<html><body><p>dnia 27.11.2026 nastąpiła płatność BLIK w kwocie 49,99 PLN.</p></body></html>';
    $raw = (new RawMessageParser)->parse(
        "From: bank@example.test\nSubject: Płatność BLIK\nContent-Type: text/html; charset=UTF-8\n\n".$body
    );

    expect((new NestBankParser)->parse('', $raw))->toBe([])
        ->and((new NestBankParser)->isRelevant('', $raw))->toBeFalse();
});
