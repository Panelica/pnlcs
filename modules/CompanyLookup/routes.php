<?php

use Illuminate\Support\Facades\Route;
use Modules\CompanyLookup\Http\CompanyLookupController;

// Public registry data proxied through the backend. The GUS API key stays
// server-side; the browser only ever sees the normalised result. Rate-limited
// because each request fans out to external registries the operator is
// answerable for.
Route::post('/api/company/lookup', [CompanyLookupController::class, 'lookup'])
    ->middleware(['web', 'throttle:30,1'])
    ->name('company.lookup');
