<?php

namespace App\Services;

use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(string $email, string $secret, string $companyName = 'PNLCS'): string
    {
        return $this->google2fa->getQRCodeUrl($companyName, $email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    public function verifyBackupCode(array $storedCodes, string $code): array
    {
        $code = Str::upper(trim($code));
        $index = array_search($code, $storedCodes);

        if ($index === false) {
            return ['valid' => false, 'remaining' => $storedCodes];
        }

        unset($storedCodes[$index]);
        return ['valid' => true, 'remaining' => array_values($storedCodes)];
    }
}
