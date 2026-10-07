<?php

namespace App\Services\Updates;

use App\Models\Admin;
use App\Models\Setting;
use Throwable;

/**
 * The bar at the bottom of admin pages that says a PNLCS release is waiting.
 *
 * Shown to administrators who may apply updates, until they hide it (for that
 * release, in their browser) or turn it off (for themselves, until they turn
 * it back on on Setup -> Updates). An update that did not finish is shown
 * whatever they chose: the site may be in maintenance until someone acts.
 */
class UpdateBar
{
    public function __construct(private readonly UpdateState $state) {}

    public static function settingKey(Admin $admin): string
    {
        return "update_bar_off.admin.{$admin->id}";
    }

    public function isOff(Admin $admin): bool
    {
        return Setting::get(self::settingKey($admin)) === '1';
    }

    /** @return array{kind: string, version?: string, installed?: string, pre_release?: bool}|null */
    public function forAdmin(?Admin $admin, ?string $routeName): ?array
    {
        // The bar must never be the reason an admin page fails to render.
        try {
            if ($admin === null || ! $admin->hasPermission('manage_updates')) {
                return null;
            }

            $unfinished = app(UpdateRunner::class)->unfinished();
            if ($unfinished !== null) {
                return ['kind' => 'unfinished', 'version' => (string) ($unfinished['to'] ?? '')];
            }

            if ($routeName === 'admin.config.updates' || $this->isOff($admin)) {
                return null;
            }

            $latest = $this->state->read('latest.json')['latest'] ?? null;
            $offered = is_array($latest) ? Version::parse((string) ($latest['version'] ?? '')) : null;
            if ($offered === null) {
                return null;
            }

            // A release installed since the last check is no longer news.
            $installed = app(Installation::class)->version();
            if ($installed !== null && ! $offered->greaterThan($installed)) {
                return null;
            }

            return ['kind' => 'available', 'version' => (string) $offered, 'installed' => (string) ($installed ?? ''), 'pre_release' => (bool) ($latest['pre_release'] ?? false)];
        } catch (Throwable) {
            return null;
        }
    }
}
