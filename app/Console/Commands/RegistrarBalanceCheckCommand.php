<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Watch the registrar float.
 *
 * A registrar account that runs dry does not fail loudly: renewals stop going
 * through and new registrations are refused, one customer at a time, while the
 * panel keeps taking orders. Better to hear about it while there is still
 * money to act on.
 */
class RegistrarBalanceCheckCommand extends Command
{
    protected $signature = 'pnlcs:registrar-balance {--force : Send the warning even if one went out today}';

    protected $description = 'Warn when the domain registrar balance falls to the configured floor';

    public function handle(): int
    {
        $registrar = (string) Setting::get('BalanceWatchRegistrar', 'domainnameapi');
        $threshold = (float) Setting::get('RegistrarBalanceThreshold', '1000');
        $currency = strtoupper((string) Setting::get('RegistrarBalanceCurrency', 'TRY'));

        $module = app(ModuleRegistry::class)->getRegistrarModule($registrar);

        if (! $module || ! method_exists($module, 'getBalance')) {
            $this->warn("No registrar module '{$registrar}' that can report a balance.");

            return self::SUCCESS;
        }

        try {
            $balance = $module->getBalance();
        } catch (\Throwable $e) {
            $this->error('Balance lookup threw: '.$e->getMessage());
            $this->alert_operator(
                __('messages.registrar_balance.unreadable_subject'),
                __('messages.registrar_balance.unreadable_body', ['registrar' => $registrar, 'error' => $e->getMessage()]),
                'unreadable'
            );

            return self::FAILURE;
        }

        if (! ($balance['success'] ?? false)) {
            $reason = (string) ($balance['message'] ?? 'unknown');
            $this->error("Balance lookup failed: {$reason}");
            $this->alert_operator(
                __('messages.registrar_balance.unreadable_subject'),
                __('messages.registrar_balance.unreadable_body', ['registrar' => $registrar, 'error' => $reason]),
                'unreadable'
            );

            return self::FAILURE;
        }

        $amount = (float) ($balance[strtolower($currency)] ?? 0);

        $this->info(sprintf('%s balance: %s %s (floor %s)', $registrar, number_format($amount, 2), $currency, number_format($threshold, 2)));

        if ($amount > $threshold) {
            Setting::set('RegistrarBalanceLastAlert', '');   // recovered

            return self::SUCCESS;
        }

        $this->warn('Balance is at or below the floor.');

        $this->alert_operator(
            __('messages.registrar_balance.low_subject', ['amount' => number_format($amount, 2), 'currency' => $currency]),
            __('messages.registrar_balance.low_body', [
                'registrar' => $registrar,
                'amount' => number_format($amount, 2),
                'currency' => $currency,
                'threshold' => number_format($threshold, 2),
            ]),
            'low'
        );

        return self::SUCCESS;
    }

    /**
     * Mail the operator, at most once a day unless forced.
     *
     * A daily cadence is the point: a warning that repeats every hour is a
     * warning nobody reads by the second day.
     */
    private function alert_operator(string $subject, string $body, string $kind): void
    {
        $today = now()->toDateString();
        $last = (string) Setting::get('RegistrarBalanceLastAlert', '');

        if (! $this->option('force') && $last === $kind.':'.$today) {
            $this->line('Warning already sent today; not repeating.');

            return;
        }

        $to = (string) (Setting::get('RegistrarBalanceAlertEmail') ?: Setting::get('SystemEmailAddress'));

        if ($to === '') {
            $this->warn('No address to warn: set SystemEmailAddress.');

            return;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Setting::set('RegistrarBalanceLastAlert', $kind.':'.$today);
            $this->info("Warning sent to {$to}.");
        } catch (\Throwable $e) {
            Log::error('Registrar balance warning could not be mailed: '.$e->getMessage());
            $this->error('Could not send the warning: '.$e->getMessage());
        }

        try {
            app(NotificationService::class)->dispatch('registrar.balance_low', [
                'event_type' => 'registrar.balance_low',
                'subject' => $subject,
                'message' => $body,
            ]);
        } catch (\Throwable) {
            // No rules configured yet - the email above is the delivery path.
        }
    }
}
