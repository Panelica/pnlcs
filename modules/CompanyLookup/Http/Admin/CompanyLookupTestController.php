<?php

namespace Modules\CompanyLookup\Http\Admin;

use Modules\CompanyLookup\CeidgCompanyProvider;
use Modules\CompanyLookup\GusCompanyProvider;
use Modules\CompanyLookup\MfVatProvider;
use Modules\CompanyLookup\OpenbrisCompanyProvider;

class CompanyLookupTestController
{
    public function test(string $provider)
    {
        $class = match ($provider) {
            'gus' => GusCompanyProvider::class,
            'mf' => MfVatProvider::class,
            'ceidg' => CeidgCompanyProvider::class,
            'openbris' => OpenbrisCompanyProvider::class,
            default => null,
        };

        if ($class === null) {
            return back()->with('error', __('messages.company_lookup.test_invalid_provider'));
        }

        $label = match ($provider) {
            'gus' => 'GUS',
            'mf' => 'MF',
            'ceidg' => 'CEIDG',
            'openbris' => 'OpenBRIS',
            default => strtoupper($provider),
        };

        $result = app($class)->testConnection();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $label.': '.$result['message']
        );
    }
}
