<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\RegistrarBalanceCheckCommand;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

/** "Check now" on the dashboard's registrar balance: runs the same check the scheduler does. */
class RegistrarBalanceController extends Controller
{
    public function check()
    {
        Artisan::call('pnlcs:registrar-balance');
        $last = RegistrarBalanceCheckCommand::last();

        return back()->with(($last['ok'] ?? false) ? 'success' : 'error', ($last['ok'] ?? false)
            ? __('admin.dashboard.balance_checked')
            : __('admin.dashboard.balance_unreadable', ['error' => (string) ($last['error'] ?? '')]));
    }
}
