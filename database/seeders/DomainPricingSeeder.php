<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomainPricingSeeder extends Seeder
{
    public function run(): void
    {
        $tlds = [
            // [extension, register, transfer, renew, grace_period]
            [".com",      10.99, 10.99, 12.99, 5 ],
            [".net",      12.99, 12.99, 14.99, 5 ],
            [".org",      11.99, 11.99, 14.99, 5 ],
            [".info",      8.99,  8.99, 10.99, 5 ],
            [".biz",       9.99,  9.99, 11.99, 5 ],
            [".us",        6.99,  6.99,  8.99, 5 ],
            [".co",       24.99, 24.99, 27.99, 5 ],
            [".io",       39.99, 39.99, 44.99, 5 ],
            [".dev",      14.99, 14.99, 16.99, 5 ],
            [".app",      14.99, 14.99, 16.99, 5 ],
            [".ai",       74.99, 74.99, 79.99, 5 ],
            [".me",       14.99, 14.99, 16.99, 5 ],
            [".tv",       29.99, 29.99, 32.99, 5 ],
            [".cc",       19.99, 19.99, 22.99, 5 ],
            [".xyz",       2.99,  9.99, 12.99, 5 ],
            [".online",    4.99,  9.99, 34.99, 5 ],
            [".site",      2.99,  9.99, 29.99, 5 ],
            [".store",    19.99, 19.99, 59.99, 5 ],
            [".tech",     14.99, 14.99, 49.99, 5 ],
            [".space",     2.99,  9.99, 24.99, 5 ],
            [".cloud",    14.99, 14.99, 19.99, 5 ],
            [".host",     59.99, 59.99, 69.99, 5 ],
            [".pro",       9.99,  9.99, 14.99, 5 ],
            [".agency",   19.99, 19.99, 29.99, 5 ],
            [".digital",  29.99, 29.99, 39.99, 5 ],
            [".email",    19.99, 19.99, 29.99, 5 ],
            [".solutions",24.99, 24.99, 34.99, 5 ],
            [".systems",  24.99, 24.99, 34.99, 5 ],
            [".network",  19.99, 19.99, 29.99, 5 ],
            [".studio",   19.99, 19.99, 29.99, 5 ],
            [".design",   29.99, 29.99, 39.99, 5 ],
            [".shop",     24.99, 24.99, 34.99, 5 ],
            [".live",     14.99, 14.99, 19.99, 5 ],
            [".world",    19.99, 19.99, 29.99, 5 ],
            [".today",     9.99,  9.99, 14.99, 5 ],
            [".media",    19.99, 19.99, 29.99, 5 ],
            [".zone",     19.99, 19.99, 29.99, 5 ],
            [".club",      7.99,  7.99, 12.99, 5 ],
            [".life",     19.99, 19.99, 29.99, 5 ],
            [".center",   19.99, 19.99, 29.99, 5 ],
            [".de",        9.99,  9.99, 12.99, 0 ],
            [".uk",        9.99,  9.99, 12.99, 0 ],
            [".fr",       12.99, 12.99, 15.99, 0 ],
            [".nl",       12.99, 12.99, 15.99, 0 ],
            [".eu",        9.99,  9.99, 12.99, 5 ],
            [".tr",       14.99, 14.99, 17.99, 0 ],
            [".ru",       12.99, 12.99, 15.99, 0 ],
            [".in",        9.99,  9.99, 12.99, 5 ],
            [".ca",       14.99, 14.99, 17.99, 5 ],
            [".au",       14.99, 14.99, 17.99, 0 ],
        ];

        $now = now();
        $sort = 0;
        foreach ($tlds as [$ext, $reg, $trans, $ren, $grace]) {
            DB::table("domain_pricing")->updateOrInsert(
                ["extension" => $ext],
                [
                    "register_price"          => $reg,
                    "transfer_price"          => $trans,
                    "renew_price"             => $ren,
                    "dns_management"          => true,
                    "email_forwarding"        => false,
                    "id_protection"           => false,
                    "epp_code"                => ($grace > 0),
                    "auto_registrar"          => null,
                    "grace_period"            => $grace,
                    "redemption_grace_period" => 30,
                    "min_years"               => 1,
                    "max_years"               => 10,
                    "sort_order"              => $sort++,
                    "enabled"                 => true,
                    "updated_at"              => $now,
                    "created_at"              => $now,
                ]
            );
        }
    }
}
