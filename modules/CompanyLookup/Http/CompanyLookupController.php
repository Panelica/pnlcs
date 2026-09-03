<?php

namespace Modules\CompanyLookup\Http;

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
        $validated = $request->validate([
            'nip' => 'required|string|max:20',
        ]);

        return response()->json($this->service->lookup((string) $validated['nip']));
    }
}
