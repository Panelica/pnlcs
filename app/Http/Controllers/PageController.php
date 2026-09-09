<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/**
 * Corporate content pages.
 *
 * Like the legal documents these live in views, not database rows: text that
 * describes what the server does should not be editable by accident from the
 * panel, and a change to it should be visible in the repository. The one
 * exception is the "about" prose, which is the operator's own words and is
 * kept in the settings so they can write it without editing a file.
 */
class PageController extends Controller
{
    /**
     * The SSL page.
     *
     * Not a product for sale but a feature every plan includes: the
     * certificate is issued and renewed on its own. Everything on the page
     * describes what the server does today - a description, not a promise.
     */
    public function ssl()
    {
        return view('pages.ssl');
    }

    public function about()
    {
        $get = fn (string $key, string $fallback = '') => trim((string) Setting::get($key, '')) ?: $fallback;

        return view('pages.about', [
            'about' => $get('AboutText'),
            'company' => [
                'name' => $get('CompanyName', config('app.name', 'PNLCS')),
                'legal_name' => $get('CompanyLegalName'),
                'address' => $get('Address'),
                'city' => $get('CompanyCity', $get('City')),
                'state' => $get('State'),
                'postcode' => $get('Postcode'),
                'trade_registry' => $get('TradeRegistryNo'),
                'country' => $get('Country'),
                'phone' => $get('PhoneNumber'),
                'email' => $get('Email', $get('SystemEmailAddress')),
                'tax_office' => $get('TaxOffice'),
                'tax_id' => $get('TaxID'),
                'mersis' => $get('MersisNo'),
                'website' => $get('SystemURL', url('/')),
            ],
        ]);
    }

    /**
     * The mail client setup guide.
     *
     * The server names on the page are derived from the brand's domain rather
     * than written in, so a change of SystemURL carries the guide with it.
     * Ports and security types describe what the panel's mail stack does:
     * dovecot on 993/995 SSL, postfix on 465 SSL and 587 STARTTLS.
     */
    public function mailSetup()
    {
        $host = parse_url((string) Setting::get('SystemURL', url('/')), PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', (string) $host) ?: (string) $host;

        return view('pages.mail-setup', [
            'mail' => [
                'domain' => $domain,
                'host' => 'mail.'.$domain,
                'webmail' => 'https://webmail.'.$domain,
                'webmail_label' => 'webmail.'.$domain,
            ],
        ]);
    }
}
