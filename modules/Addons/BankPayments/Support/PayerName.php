<?php

namespace Modules\Addons\BankPayments\Support;

use Illuminate\Support\Str;

final class PayerName
{
    public static function normalize(?string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);

        foreach ([
            'spolka z ograniczona odpowiedzialnoscia spolka komandytowa',
            'spolka z ograniczona odpowiedzialnoscia',
            'spolka akcyjna',
            'sp z o o',
            'sp z oo',
            'spolka komandytowa',
            'spolka jawna',
            'spolka cywilna',
            'limited liability company',
            'limited',
            'ltd',
            'llc',
            'inc',
            'gmbh',
            'company',
        ] as $legalForm) {
            $value = str_replace($legalForm, ' ', $value);
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
