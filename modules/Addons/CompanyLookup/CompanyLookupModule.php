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

        $testButton = function (string $provider): string {
            return '<form method="POST" action="'.route('admin.config.addons.modules.company-lookup.test', $provider).'" style="margin:0;">'
                .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                .'<button type="submit" class="btn btn-success btn-sm" style="font-size:12px;padding:4px 12px;font-weight:600;">'.__('messages.company_lookup.test').'</button>'
                .'</form>';
        };

        $sourceRow = function (string $label, string $value, string $provider, string $statusHtml): string {
            return '<tr>'
                .'<td style="padding:8px;color:var(--pn-muted);">'.e($label).'</td>'
                .'<td style="padding:8px;">'.$statusHtml.'</td>'
                .'<td style="padding:8px;text-align:right;">'.$value.'</td>'
                .'<td style="padding:8px;text-align:right;white-space:nowrap;">'.$provider.'</td>'
                .'</tr>';
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
                .'<td style="padding:6px 8px;" colspan="3">'.e((string) $value).'</td></tr>';
        }

        $html = '<p style="font-size:13px;color:var(--pn-muted);margin:0 0 12px;">'
            .__('messages.company_lookup.addon_output_hint').'</p>';
        $html .= '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
        $html .= '<tr><th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.company_lookup.source').'</th>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.company_lookup.status').'</th>'
            .'<th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.company_lookup.endpoint').'</th>'
            .'<th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);"></th></tr>';

        $html .= $sourceRow('GUS', $s['gus']['endpoint'], $testButton('gus'), $badge(filled($s['gus']['key'] ?? null)));
        $html .= $sourceRow('MF', $s['mf']['endpoint'], $testButton('mf'), '<span style="color:#46a546;">'.__('messages.company_lookup.key_configured').'</span>');
        $html .= $sourceRow('CEIDG', $s['ceidg']['endpoint'], $testButton('ceidg'), $badge(filled($s['ceidg']['key'] ?? null)));
        $html .= $sourceRow('OpenBRIS', $s['openbris']['endpoint'], $testButton('openbris'), $badge(filled($s['openbris']['key'] ?? null)));
        $html .= $rows;
        $html .= '</table>';

        return $html;
    }
}
