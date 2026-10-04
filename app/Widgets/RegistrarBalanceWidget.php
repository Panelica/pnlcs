<?php

namespace App\Widgets;

use App\Console\Commands\RegistrarBalanceCheckCommand;
use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;

/**
 * The registrar float on the dashboard: the last balance the twice-daily
 * check read (pnlcs:registrar-balance), red at or below the floor. It never
 * calls the registrar itself; "Check now" runs the check.
 */
class RegistrarBalanceWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return __('admin.dashboard.w_registrar_balance'); }
    public function getDescription(): string { return __('admin.dashboard.w_registrar_balance_desc'); }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 65; }
    public function getPermission(): ?string { return Permissions::MANAGE_REGISTRARS; }
    public function getCacheTtl(): int { return 0; }

    public function getData(): array
    {
        return [
            'off' => RegistrarBalanceCheckCommand::switchedOff(),
            'configured' => RegistrarBalanceCheckCommand::configured(),
            'last' => RegistrarBalanceCheckCommand::last(),
        ];
    }

    public function render(array $data): string
    {
        $last = $data['last'];
        // Switched off on purpose is not "not set up": say which it is, and
        // where to turn it back on. Both used to read "not set up yet".
        if ($data['off'] ?? false) {
            return '<div style="padding:12px 16px;font-size:13px;color:var(--pn-muted);">'.e(__('admin.dashboard.balance_watch_off'))
                .' <a href="'.e(route('admin.settings.general')).'#settings-registrar_balance">'.e(__('admin.settings.registrar_balance')).'</a></div>';
        }
        if (! ($data['configured'] ?? true)) {
            // Nothing to check: say so once, with the way to set it up, and no button.
            return '<div style="padding:12px 16px;font-size:13px;color:var(--pn-muted);">'.e(__('admin.dashboard.balance_not_configured'))
                .' <a href="'.e(route('admin.config.registrars')).'">'.e(__('admin.registrars.title')).'</a></div>';
        }
        $button = '<form method="POST" action="'.e(route('admin.config.registrar-balance.check')).'" style="margin:0;">'.csrf_field()
            .'<button type="submit" class="btn btn-default btn-xs">'.e(__('admin.dashboard.check_now')).'</button></form>';
        $row = fn (string $left, string $right) => '<div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;">'.$left.$right.'</div>';

        if (! $last) {
            return $row('<span style="color:var(--pn-muted);">'.e(__('admin.dashboard.balance_never_read')).'</span>', $button);
        }

        $at = \Illuminate\Support\Carbon::parse($last['at'])->timezone(display_tz())->format(datetime_fmt());
        if (! ($last['ok'] ?? false)) {
            return $row('<span style="color:#c43c35;">'.e(__('admin.dashboard.balance_unreadable', ['error' => (string) ($last['error'] ?? '')])).'</span>', $button)
                .'<div style="padding:0 16px 12px;font-size:11px;color:var(--pn-muted);">'.e(__('admin.dashboard.balance_checked_at', ['at' => $at])).'</div>';
        }

        $low = (float) $last['amount'] <= (float) $last['threshold'];
        $amount = '<b style="font-size:18px;'.($low ? 'color:#c43c35;' : '').'">'.e(number_format((float) $last['amount'], 2)).' '.e($last['currency']).'</b>';
        // A registrar with a second wallet (DomainNameAPI keeps TRY and USD
        // apart): named as a wallet, so it is not read as the balance
        // converted, and left out while it is empty.
        $other = $last['currency'] !== 'USD' && (float) ($last['usd'] ?? 0) != 0.0
            ? ' <span style="color:var(--pn-muted);">· '.e(__('admin.dashboard.balance_usd_wallet', ['amount' => number_format((float) $last['usd'], 2)])).'</span>'
            : '';

        return $row('<span>'.$amount.$other.'</span>', $button)
            .'<div style="padding:0 16px 12px;font-size:11px;color:var(--pn-muted);">'
            .e(__('admin.dashboard.balance_floor', ['floor' => number_format((float) $last['threshold'], 2).' '.$last['currency']]))
            .' · '.e(__('admin.dashboard.balance_checked_at', ['at' => $at]))
            .($low ? ' · <span style="color:#c43c35;">'.e(__('admin.dashboard.balance_low')).'</span>' : '').'</div>';
    }
}
