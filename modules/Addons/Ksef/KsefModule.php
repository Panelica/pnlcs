<?php

namespace Modules\Addons\Ksef;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Modules\Ksef\KsefSettings;

/**
 * KSeF (Krajowy System e-Faktur) addon.
 *
 * Paid invoices are handed to the Polish national e-invoicing system. The
 * submission status list lives in its own screen under Billing → KSeF status
 * (`admin.ksef.index`), shown only while this addon is active.
 */
class KsefModule implements AddonModuleInterface
{
    public function getName(): string { return 'ksef'; }

    public function getDisplayName(): string { return __('messages.ksef.settings_title'); }

    public function getDescription(): string { return __('messages.ksef.addon_description'); }

    public function getVersion(): string { return '1.0.0'; }

    public function getAuthor(): string { return 'PNLCS'; }

    public function activate(): array
    {
        return ['success' => true, 'message' => __('messages.ksef.activated')];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => __('messages.ksef.deactivated')];
    }

    public function config(): array
    {
        return KsefSettings::fields();
    }

    public function sidebar(): array
    {
        return [];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => 'KSeF upgraded from '.e($fromVersion)];
    }

    public function output(Request $request): string
    {
        return '<p style="font-size:13px;color:var(--pn-muted);margin:0;">'.__('messages.ksef.addon_moved_hint').'</p>'
            .'<p style="margin-top:12px;">'
            .'<a href="'.route('admin.ksef.index').'" class="btn btn-primary btn-sm" style="font-size:13px;">'.__('admin.nav.ksef_status').'</a>'
            .'</p>';
    }
}
