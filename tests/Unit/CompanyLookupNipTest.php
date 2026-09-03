<?php

namespace Tests\Unit;

use Modules\CompanyLookup\Support\Nip;
use PHPUnit\Framework\TestCase;

class CompanyLookupNipTest extends TestCase
{
    public function test_valid_nip_passes(): void
    {
        $this->assertTrue(Nip::isValid('5261040828'));
    }

    public function test_invalid_checksum_is_rejected(): void
    {
        $this->assertFalse(Nip::isValid('1234567890'));
    }

    public function test_nip_with_spaces_passes(): void
    {
        $this->assertTrue(Nip::isValid('526 104 08 28'));
    }

    public function test_nip_with_dashes_passes(): void
    {
        $this->assertTrue(Nip::isValid('526-104-08-28'));
    }

    public function test_nip_with_mixed_separators_passes(): void
    {
        $this->assertTrue(Nip::isValid('526/104.08-28'));
    }

    public function test_too_short_is_rejected(): void
    {
        $this->assertFalse(Nip::isValid('526104082'));
    }

    public function test_too_long_is_rejected(): void
    {
        $this->assertFalse(Nip::isValid('52610408281'));
    }

    public function test_non_digits_are_rejected(): void
    {
        $this->assertFalse(Nip::isValid('abcdefghij'));
    }

    public function test_normalize_strips_separators(): void
    {
        $this->assertSame('5261040828', Nip::normalize('526-104 08/28'));
    }
}
