<?php

use App\Console\Commands\RegistrarBalanceCheckCommand;
use Modules\Registrars\HRD\HrdClient;
use Modules\Registrars\HRD\HrdRegistrar;

/*
 * #329. The registrar balance watch lists the registrars that have
 * getBalance(). HRD's client could read the partner balance (the Test button
 * shows it), but the registrar had no getBalance(), so HRD could not be
 * watched and a drained balance was found through a failed renewal.
 */

function hrdWithClient(HrdClient $client): HrdRegistrar
{
    $registrar = new HrdRegistrar;
    $property = new ReflectionProperty(HrdRegistrar::class, 'client');
    $property->setValue($registrar, $client);

    return $registrar;
}

it('reports the HRD partner balance in PLN, the key the watch reads', function () {
    $client = new class('login', 'pass', '00') extends HrdClient {
        public function partnerGetBalance(): array
        {
            return ['balance' => '1234.56'];
        }
    };

    expect(hrdWithClient($client)->getBalance())->toBe(['success' => true, 'pln' => '1234.56', 'message' => null]);
});

it('says why when the balance cannot be read', function () {
    $client = new class('login', 'pass', '00') extends HrdClient {
        public function partnerGetBalance(): array
        {
            throw new RuntimeException('HRD: connection refused');
        }
    };

    expect(hrdWithClient($client)->getBalance())->toBe(['success' => false, 'message' => 'HRD: connection refused']);
});

it('offers HRD in the registrar balance watch', function () {
    expect(RegistrarBalanceCheckCommand::watchable())->toContain('hrd');
});
