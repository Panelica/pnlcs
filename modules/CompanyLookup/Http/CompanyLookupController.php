<?php

namespace Modules\CompanyLookup\Http;

use App\Services\AddonManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CompanyLookup\CompanyLookupService;

class CompanyLookupController
{
    public function __construct(private readonly CompanyLookupService $service)
    {
    }

    public function lookup(Request $request): JsonResponse
    {
        // The route is loaded whether or not the addon is on (its provider is
        // a core one); the form only shows the button while it is. Switched
        // off, the endpoint is not there either: no lookups on the operator's
        // registry keys for an addon they have turned off.
        abort_unless(app(AddonManager::class)->isActive('company_lookup'), 404);

        $validated = $request->validate([
            'nip' => 'required|string|max:20',
        ]);

        return response()->json($this->service->lookup((string) $validated['nip']));
    }
}
