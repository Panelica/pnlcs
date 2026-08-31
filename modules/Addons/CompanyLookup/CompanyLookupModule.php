<?php

namespace Modules\Addons\CompanyLookup;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Modules\CompanyLookup\CompanyLookupSettings;

/**
 * Company Lookup (NIP) addon.
 *
 * A drop-in extension built on the addon skeleton: it declares its config via
 * config() and the AddonController renders + persists it into the generic
 * addon_settings store. The lookup service reads the same store at request time
 * (CompanyLookupSettings::resolve()).
 */
class CompanyLookupModule implements AddonModuleInterface
{
    public function getName(): string { return 'company_lookup'; }

    public function getDisplayName(): string { return __('messages.company_lookup.settings_title'); }

    public function getDescription(): string { return __('messages.company_lookup.addon_description'); }

    public function getVersion(): string { return '1.0.0'; }

    public function getAuthor(): string { return 'PNLCS'; }

    public function activate(): array
    {
        return ['success' => true, 'message' => __('messages.company_lookup.activated')];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => __('messages.company_lookup.deactivated')];
    }

    public function config(): array
    {
        return CompanyLookupSettings::fields();
    }

    public function sidebar(): array
    {
        return [];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => 'Company Lookup upgraded from '.e($fromVersion)];
    }

    public function output(Request $request): string
    {
        $s = CompanyLookupSettings::resolve();

        $badge = function (bool $configured): string {
            return $configured
                ? '<span style="color:#46a546;font-weight:600;">'.__('messages.company_lookup.key_configured').'</span>'
                : '<span style="color:#c43c35;font-weight:600;">'.__('messages.company_lookup.key_missing').'</span>';
        };

        $rows = '';
        foreach ([
            __('messages.company_lookup.gus_endpoint') => $s['gus']['endpoint'],
            __('messages.company_lookup.mf_endpoint') => $s['mf']['endpoint'],
            __('messages.company_lookup.ceidg_endpoint') => $s['ceidg']['endpoint'],
            __('messages.company_lookup.openbris_endpoint') => $s['openbris']['endpoint'],
            __('messages.company_lookup.cache_ttl') => $s['cache_ttl'].' s',
            __('messages.company_lookup.connect_timeout') => $s['http']['connect_timeout'].' s',
            __('messages.company_lookup.request_timeout') => $s['http']['request_timeout'].' s',
        ] as $label => $value) {
            $rows .= '<tr><td style="padding:6px 8px;color:var(--pn-muted);">'.e((string) $label).'</td>'
                .'<td style="padding:6px 8px;">'.e((string) $value).'</td></tr>';
        }

        $html = '<p style="font-size:13px;color:var(--pn-muted);margin:0 0 12px;">'
            .__('messages.company_lookup.addon_output_hint').'</p>';
        $html .= '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:6px 8px;color:var(--pn-muted);">'.__('messages.company_lookup.gus_api_key').'</td>'
            .'<td style="padding:6px 8px;">'.$badge(filled($s['gus']['key'] ?? null)).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;color:var(--pn-muted);">'.__('messages.company_lookup.ceidg_api_key').'</td>'
            .'<td style="padding:6px 8px;">'.$badge(filled($s['ceidg']['key'] ?? null)).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;color:var(--pn-muted);">'.__('messages.company_lookup.openbris_api_key').'</td>'
            .'<td style="padding:6px 8px;">'.$badge(filled($s['openbris']['key'] ?? null)).'</td></tr>';
        $html .= $rows;
        $html .= '</table>';

        return $html;
    }
}
