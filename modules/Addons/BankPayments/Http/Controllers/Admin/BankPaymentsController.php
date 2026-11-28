<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AddonManager;

abstract class BankPaymentsController extends Controller
{
    protected function ensureActive(): void
    {
        abort_unless(app(AddonManager::class)->isActive('bank_payments'), 404);
    }
}
