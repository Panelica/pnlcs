<?php

use Modules\Ksef\Support\Nip;

it('strips the PL prefix and separators from a NIP', function () {
    expect(Nip::normalize('PL6482409327'))->toBe('6482409327')
        ->and(Nip::normalize('pl 648-240-93-27'))->toBe('6482409327')
        ->and(Nip::isValid('PL6482409327'))->toBeTrue();
});

it('rejects an invalid NIP', function () {
    expect(Nip::isValid('PL1234567890'))->toBeFalse()
        ->and(Nip::isValid('648240932'))->toBeFalse()
        ->and(Nip::isValid('6482409328'))->toBeFalse();
});
