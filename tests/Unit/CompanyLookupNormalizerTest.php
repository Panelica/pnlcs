<?php

namespace Tests\Unit;

use Modules\CompanyLookup\CompanyData;
use Modules\CompanyLookup\DataNormalizer;
use PHPUnit\Framework\TestCase;

class CompanyLookupNormalizerTest extends TestCase
{
    private function data(array $overrides = []): CompanyData
    {
        return new CompanyData(...$overrides);
    }

    public function test_identification_priority_is_gus_then_ceidg_then_mf(): void
    {
        $gus = $this->data(['name' => 'GUS NAME', 'regon' => '111111111']);
        $ceidg = $this->data(['name' => 'CEIDG NAME', 'regon' => '222222222']);
        $mf = $this->data(['name' => 'MF NAME', 'regon' => '333333333']);

        $result = (new DataNormalizer())->normalize($gus, $ceidg, $mf);

        expect($result['company']->name)->toBe('GUS NAME')
            ->and($result['company']->regon)->toBe('111111111');
    }

    public function test_ceidg_fills_the_gap_when_gus_is_missing(): void
    {
        $ceidg = $this->data(['name' => 'CEIDG NAME', 'nip' => '5261040828']);
        $mf = $this->data(['name' => 'MF NAME']);

        $result = (new DataNormalizer())->normalize(null, $ceidg, $mf);

        expect($result['company']->name)->toBe('CEIDG NAME')
            ->and($result['company']->nip)->toBe('5261040828');
    }

    public function test_vat_status_and_bank_accounts_come_from_mf(): void
    {
        $gus = $this->data(['name' => 'X']);
        $mf = $this->data(['vatStatus' => 'Czynny', 'bankAccounts' => ['111']]);

        $result = (new DataNormalizer())->normalize($gus, null, $mf);

        expect($result['company']->vatStatus)->toBe('Czynny')
            ->and($result['company']->bankAccounts)->toBe(['111']);
    }

    public function test_business_status_and_dates_come_from_ceidg(): void
    {
        $ceidg = $this->data([
            'businessStatus' => 'AKTYWNY',
            'activityStartDate' => '2020-01-01',
            'suspensionStartDate' => '2021-05-05',
        ]);

        $result = (new DataNormalizer())->normalize(null, $ceidg, null);

        expect($result['company']->businessStatus)->toBe('AKTYWNY')
            ->and($result['company']->activityStartDate)->toBe('2020-01-01')
            ->and($result['company']->suspensionStartDate)->toBe('2021-05-05');
    }

    public function test_discrepancy_is_reported_not_silently_discarded(): void
    {
        $gus = $this->data(['name' => 'NAME A']);
        $ceidg = $this->data(['name' => 'NAME B']);

        $result = (new DataNormalizer())->normalize($gus, $ceidg, null);

        expect($result['company']->name)->toBe('NAME A') // GUS wins
            ->and($result['warnings'])->toHaveCount(1)
            ->and($result['warnings'][0]['field'])->toBe('name')
            ->and($result['warnings'][0]['sources'])->toBe(['gus' => 'NAME A', 'ceidg' => 'NAME B']);
    }

    public function test_no_warning_when_sources_agree(): void
    {
        $gus = $this->data(['name' => 'SAME', 'regon' => '111111111']);
        $mf = $this->data(['name' => 'SAME']);

        $result = (new DataNormalizer())->normalize($gus, null, $mf);

        expect($result['warnings'])->toBe([]);
    }

    public function test_pkd_priority_is_gus_then_ceidg(): void
    {
        $gus = $this->data(['pkd' => ['62.01.Z']]);
        $ceidg = $this->data(['pkd' => ['62.02.Z']]);

        $result = (new DataNormalizer())->normalize($gus, $ceidg, null);

        expect($result['company']->pkd)->toBe(['62.01.Z']);
    }

    public function test_openbris_is_the_last_resort_for_identification(): void
    {
        $mf = $this->data(['name' => 'MF NAME']);
        $openbris = $this->data(['name' => 'OPENBRIS NAME', 'city' => 'Kraków']);

        // GUS and CEIDG absent; MF wins over OpenBRIS for the name.
        $result = (new DataNormalizer())->normalize(null, null, $mf, $openbris);

        expect($result['company']->name)->toBe('MF NAME')
            ->and($result['company']->city)->toBe('Kraków');
    }
}
