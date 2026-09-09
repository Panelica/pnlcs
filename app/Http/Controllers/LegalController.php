<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * The legal documents a hosting company has to put in front of a visitor
 * before it can take their money.
 *
 * Held as views rather than database rows on purpose: these are versioned
 * documents that must be reviewable in the repository and cannot be edited
 * away by accident from an admin screen. A customer who agreed to a set of
 * terms is entitled to see the text they agreed to, so every change to one of
 * these files should be a deliberate, dated commit.
 *
 * Two audiences: Turkish law requires a Mesafeli Satis Sozlesmesi, an
 * On Bilgilendirme Formu and a KVKK notice; customers outside Turkey need
 * GDPR/UK-GDPR, CCPA and ICANN wording. Both sets live here and the shown
 * language follows the visitor's own.
 */
class LegalController extends Controller
{
    /**
     * Every document, in the order the hub lists them.
     *
     * 'tr_only' marks the three statutory Turkish forms: they are shown to
     * everyone (a foreign customer buying from a Turkish seller is still
     * covered by them) but their body stays Turkish, because a translated
     * statutory form is not the form.
     */
    public const DOCUMENTS = [
        'terms' => [
            'tr' => ['Kullanım Koşulları ve Hizmet Sözleşmesi', 'Hizmetlerimizi kullanırken sizin ve bizim yükümlülüklerimiz.'],
            'en' => ['Terms of Service', 'The obligations on you and on us when you use our services.'],
            'icon' => 'ri-file-text-line',
        ],
        'aup' => [
            'tr' => ['Kabul Edilebilir Kullanım Politikası', 'Sunucularımızda neyin yapılabileceği, neyin yapılamayacağı.'],
            'en' => ['Acceptable Use Policy', 'What may and may not be done on our servers.'],
            'icon' => 'ri-shield-check-line',
        ],
        'privacy' => [
            'tr' => ['Gizlilik Politikası', 'Hangi kişisel verinizi, neden ve ne kadar süreyle işliyoruz.'],
            'en' => ['Privacy Policy', 'What personal data we process, why, and for how long.'],
            'icon' => 'ri-lock-line',
        ],
        'cookies' => [
            'tr' => ['Çerez Politikası', 'Sitede kullanılan çerezler ve bunları nasıl yönetebileceğiniz.'],
            'en' => ['Cookie Policy', 'The cookies this site sets and how you can control them.'],
            'icon' => 'ri-cake-2-line',
        ],
        'refund' => [
            'tr' => ['İptal, Cayma ve İade Politikası', 'Para iadesi koşulları ve 14 günlük cayma hakkı.'],
            'en' => ['Cancellation and Refund Policy', 'Refund conditions and the 14-day right of withdrawal.'],
            'icon' => 'ri-refund-2-line',
        ],
        'sla' => [
            'tr' => ['Hizmet Seviyesi Taahhüdü (SLA)', 'Çalışma süresi taahhüdümüz ve tutmazsa ne olacağı.'],
            'en' => ['Service Level Agreement', 'Our uptime commitment and what happens if we miss it.'],
            'icon' => 'ri-timer-line',
        ],
        'dpa' => [
            'tr' => ['Veri İşleme Sözleşmesi (DPA)', 'KVKK ve GDPR kapsamında veri işleyen sıfatıyla taahhütlerimiz.'],
            'en' => ['Data Processing Addendum', 'Our commitments as a processor under GDPR and Turkish data protection law.'],
            'icon' => 'ri-database-2-line',
        ],
        'abuse' => [
            'tr' => ['Kötüye Kullanım ve Telif İhlali Bildirimi', 'Şikâyet nasıl yapılır, nasıl işlenir, ne kadar sürede sonuçlanır.'],
            'en' => ['Abuse and Copyright (DMCA) Policy', 'How to file a complaint, how we handle it, and how long it takes.'],
            'icon' => 'ri-alarm-warning-line',
        ],
        'domain' => [
            'tr' => ['Alan Adı Kayıt Sözleşmesi', 'ICANN kuralları, WHOIS, yenileme ve alan adı uyuşmazlıkları.'],
            'en' => ['Domain Registration Agreement', 'ICANN rules, WHOIS, renewals and domain disputes.'],
            'icon' => 'ri-global-line',
        ],
        'kvkk' => [
            'tr' => ['KVKK Aydınlatma Metni', '6698 sayılı Kanun kapsamında veri sorumlusu aydınlatma yükümlülüğü.'],
            'en' => ['Turkish Data Protection Notice (KVKK)', 'The controller disclosure required by Turkish Law No. 6698.'],
            'icon' => 'ri-scales-3-line',
            'tr_only' => true,
        ],
        'distance-sales' => [
            'tr' => ['Mesafeli Satış Sözleşmesi', '6502 sayılı Tüketicinin Korunması Hakkında Kanun uyarınca zorunlu sözleşme.'],
            'en' => ['Distance Sales Agreement', 'The contract required by Turkish Consumer Protection Law No. 6502.'],
            'icon' => 'ri-contract-line',
            'tr_only' => true,
        ],
        'pre-information' => [
            'tr' => ['Ön Bilgilendirme Formu', 'Sipariş öncesi yasal olarak verilmesi gereken bilgiler.'],
            'en' => ['Pre-Contractual Information Form', 'The information Turkish law requires before an order is placed.'],
            'icon' => 'ri-information-line',
            'tr_only' => true,
        ],
        'yer-saglayici' => [
            'tr' => ['Yer Sağlayıcı Bilgileri', '5651 sayılı Kanun kapsamındaki BTK yer sağlayıcılık bildirimimiz ve doğrulama bağlantısı.'],
            'en' => ['Hosting Provider Registration', 'Our hosting provider notification to the Turkish ICTA under Law No. 5651.'],
            'icon' => 'ri-verified-badge-line',
            'tr_only' => true,
        ],
    ];

    /**
     * Last substantive revision. Shown on every page and used as the
     * "yururluk tarihi" — a legal document with no date on it is worth very
     * little in a dispute, because neither side can say which text applied.
     */
    public const REVISED = '2026-08-30';

    /**
     * The documents this installation publishes.
     *
     * The Turkish statutory forms make no sense on an installation whose
     * seller is elsewhere, so they are listed only when the shop's country is
     * Turkey. A slug that is not listed is not served either.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function published(): array
    {
        $turkish = strtoupper(trim((string) Setting::get('Country', ''))) === 'TR';

        return array_filter(self::DOCUMENTS, fn ($doc) => $turkish || empty($doc['tr_only']));
    }

    /** The hub: every document, grouped, with what each one is for. */
    public function index()
    {
        return view('legal.index', $this->shared());
    }

    /** One document. Unknown slugs 404 rather than showing an empty page. */
    public function show(string $document)
    {
        if (! isset(self::published()[$document])) {
            abort(404);
        }

        $locale = $this->locale();
        $view = "legal.{$locale}.{$document}";

        // A document not written in the visitor's language falls back to the
        // other one rather than 404ing: the wrong language is bad, no text at
        // all is worse. The Turkish statutory forms have no English body on
        // purpose - a translated statutory form is not the form.
        if (! view()->exists($view)) {
            $view = $locale === 'en' ? "legal.tr.{$document}" : "legal.en.{$document}";
        }

        if (! view()->exists($view)) {
            abort(404);
        }

        return view('legal.document', $this->shared() + [
            'document' => $document,
            'body' => $view,
            'meta' => self::DOCUMENTS[$document],
        ]);
    }

    /**
     * Which body to show: Turkish for a Turkish reader, English for everyone
     * else. The documents exist in those two languages; a Polish visitor is
     * better served by English than by a machine translation of a contract.
     */
    private function locale(): string
    {
        return app()->getLocale() === 'tr' ? 'tr' : 'en';
    }

    /**
     * What every legal view needs.
     *
     * The seller's identity is read from the general settings rather than
     * written into the documents, so that filling in the company address once
     * corrects it in all twelve at the same time — and so that a document can
     * never quietly disagree with the invoice, which reads the same settings.
     */
    private function shared(): array
    {
        return [
            'legalLocale' => $this->locale(),
            'documents' => self::published(),
            'revised' => self::REVISED,
            'company' => $this->company(),
        ];
    }

    /**
     * How the seller is named in the opening line of a document.
     *
     * The brand is a trading name; the party to the contract is the company
     * behind it. Written once here so that twelve documents cannot end up
     * naming the counterparty three different ways.
     */
    public static function partyName(array $company, bool $tr): string
    {
        $brand = $company['name'];
        $legal = $company['legal_name'];

        if ($legal === '' || $legal === $brand) {
            return $brand;
        }

        return $tr
            ? $legal.' ("'.$brand.'")'
            : $legal.', trading as '.$brand;
    }

    /** @return array<string, string> */
    private function company(): array
    {
        $get = fn (string $key, string $fallback = '') => trim((string) Setting::get($key, '')) ?: $fallback;

        return [
            // The brand a customer recognises, and the company that actually
            // contracts with them. Turkish consumer law wants the registered
            // title on the contract; the customer wants to see the name on the
            // website. Both are carried, and the documents say which is which.
            'name' => $get('CompanyName', config('app.name', 'PNLCS')),
            'legal_name' => $get('CompanyLegalName'),
            'address' => $get('Address', $get('CompanyAddress')),
            // CompanyCity, not City: that is the key the general settings
            // screen writes and the one the invoice reads, and the seller
            // block must never disagree with the invoice.
            'city' => $get('CompanyCity', $get('City')),
            'state' => $get('State'),
            'postcode' => $get('Postcode'),
            'country' => $get('Country', $get('CompanyCountry')),
            'phone' => $get('PhoneNumber'),
            'email' => $get('Email', $get('SystemEmailAddress')),
            'tax_office' => $get('TaxOffice'),
            'tax_id' => $get('TaxID'),
            'mersis' => $get('MersisNo'),
            'trade_registry' => $get('TradeRegistryNo'),
            'website' => $get('SystemURL', url('/')),
            'abuse_email' => $get('AbuseEmail', $get('Email')),
            'kvkk_email' => $get('DpoEmail', $get('Email')),
        ];
    }
}
