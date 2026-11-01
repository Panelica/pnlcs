<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use Modules\Registrars\HRD\HrdClient;
use Modules\Registrars\HRD\HrdRegistrar;

/*
 * #375. The partner purchase prices are pulled from HRD and written, with the
 * operator's margin, onto the TLDs whose auto registrar is HRD.
 *
 * Two things the panel has to be careful about: HRD quotes in złoty, so the
 * numbers only belong in a price list the shop reads in złoty; and the PESEL
 * now comes from the National ID field rather than a mapped custom field, so
 * an installation that mapped one must keep registering domains.
 */

/** The pricings answer HRD's getPricings returns, as an xpath over it. */
function hrdPricings(string $xml): HrdClient
{
    return new class('login', 'pass', '00', $xml) extends HrdClient
    {
        public function __construct(string $login, string $pass, string $hash, private string $xml)
        {
            parent::__construct($login, $pass, $hash);
        }

        protected function send(DOMDocument $dom): DOMXPath
        {
            $response = new DOMDocument;
            $response->loadXML($this->xml);

            return new DOMXPath($response);
        }
    };
}

function hrdRegistrarWith(HrdClient $client, array $settings = []): HrdRegistrar
{
    foreach ($settings as $name => $value) {
        RegistrarSettings::updateOrCreate(['registrar' => 'hrd', 'setting' => $name], ['value' => $value]);
    }

    $registrar = new HrdRegistrar;
    (new ReflectionProperty(HrdRegistrar::class, 'client'))->setValue($registrar, $client);

    return $registrar;
}

function hrdTld(string $extension, string $registrar = 'hrd', array $prices = []): DomainPricing
{
    return DomainPricing::create(array_merge([
        'extension' => $extension,
        'auto_registrar' => $registrar,
        'register_price' => 1,
        'renew_price' => 1,
        'transfer_price' => 1,
        'restore_price' => 1,
    ], $prices));
}

function zlotyShop(string $code = 'PLN'): void
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => $code], ['name' => $code, 'prefix' => '', 'suffix' => ' '.$code, 'rate' => 1.0, 'is_default' => true]);
}

const HRD_PRICINGS = <<<'XML'
<api>
  <partner><pricings>
    <pricing>
      <name>d_pl</name>
      <price><name>Rejestracja</name><value>46.00</value></price>
      <price><name>Odnowienie</name><value>46.00</value></price>
      <price><name>Transfer</name><value>0.00</value></price>
      <price><name>Odnowienie wygasłej</name><value>55.00</value></price>
      <price><name>Odnowienie z kwarantanny</name><value>300.00</value></price>
    </pricing>
    <pricing>
      <name>d_com.pl</name>
      <price><name>Rejestracja</name><value>10.50</value></price>
      <price><name>Odnowienie</name><value>(brak)</value></price>
    </pricing>
    <pricing>
      <name>ssl_rapidssl</name>
      <price><name>Rejestracja</name><value>99.00</value></price>
    </pricing>
  </pricings></partner>
</api>
XML;

it('reads the domain prices out of the pricings answer', function () {
    $prices = hrdPricings(HRD_PRICINGS)->partnerGetPriceList();

    expect(array_keys($prices))->toBe(['pl', 'com.pl'])
        ->and($prices['pl'])->toBe(['register' => '46.00', 'renew' => '46.00', 'transfer' => '0.00', 'restore' => '55.00'])
        // "(brak)" is HRD for "no such price"; quarantine renewal is not imported.
        ->and($prices['com.pl'])->toBe(['register' => '10.50', 'renew' => null, 'transfer' => null, 'restore' => null]);
});

it('writes the prices with the margin, rounded up, onto its own TLDs only', function () {
    zlotyShop();
    $pl = hrdTld('.pl');
    $comPl = hrdTld('.com.pl');
    $other = hrdTld('.de', 'openprovider');

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS), ['margin_percent' => '20'])->syncPrices();

    expect($result['success'])->toBeTrue()
        ->and($result['updated'])->toBe(2)
        // 46 * 1.20 = 55.2, rounded up to a whole złoty.
        ->and((float) $pl->fresh()->register_price)->toBe(56.0)
        ->and((float) $pl->fresh()->restore_price)->toBe(66.0)
        // A price HRD does not charge stays at zero, not at the old number.
        ->and((float) $pl->fresh()->transfer_price)->toBe(0.0)
        // HRD publishes no renewal price for .com.pl: the operator's own
        // number stays rather than becoming zero.
        ->and((float) $comPl->fresh()->renew_price)->toBe(1.0)
        ->and((float) $comPl->fresh()->register_price)->toBe(13.0)
        // Another registrar's TLD is not this sync's business.
        ->and((float) $other->fresh()->register_price)->toBe(1.0);
});

it('counts a TLD HRD has no price for as skipped and an unchanged one as unchanged', function () {
    zlotyShop();
    hrdTld('.xyz');
    hrdTld('.pl', 'hrd', ['register_price' => 46, 'renew_price' => 46, 'transfer_price' => 0, 'restore_price' => 55]);

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS))->syncPrices();

    expect($result)->toMatchArray(['success' => true, 'updated' => 0, 'unchanged' => 1, 'skipped' => 1]);
});

it('refuses to write złoty prices into a shop that prices in something else', function () {
    zlotyShop('USD');
    $pl = hrdTld('.pl');

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS), ['margin_percent' => '20'])->syncPrices();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('PLN')
        ->and($result['message'])->toContain('USD')
        // Nothing was touched.
        ->and((float) $pl->fresh()->register_price)->toBe(1.0);
});

/** The id number HRD is given for this client's registrant. */
function hrdIdNumberFor(Client $client, array $settings = []): string
{
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'example.pl']);

    $capturing = new class('login', 'pass', '00') extends HrdClient
    {
        public string $idNumber = '';

        public function userCreate(string $type, string $idNumber, string $email, ?string $mobilePhone, string $landlinePhone, ?string $fax, string $name, string $street, string $postcode, string $city, string $country, ?string $representative = null): int
        {
            $this->idNumber = $idNumber;

            return 42;
        }
    };

    $registrar = hrdRegistrarWith($capturing, $settings);
    $createUser = new ReflectionMethod(HrdRegistrar::class, 'createUser');
    $createUser->invoke($registrar, $domain, []);

    return $capturing->idNumber;
}

it('identifies a private person by the National ID field', function () {
    $client = Client::factory()->create(['client_type' => 'individual', 'company_name' => null, 'national_id' => '85010112345']);

    expect(hrdIdNumberFor($client))->toBe('85010112345');
});

it('still reads a PESEL custom field an installation mapped before the National ID field', function () {
    // The option is gone from the screen (#375), but the value its customers'
    // records hold is not, and an empty PESEL is a domain the registry refuses.
    $client = Client::factory()->create(['client_type' => 'individual', 'company_name' => null, 'national_id' => null]);
    $field = CustomField::create(['type' => 'client', 'field_name' => 'PESEL', 'field_type' => 'text']);
    CustomFieldValue::create(['field_id' => $field->id, 'rel_id' => $client->id, 'value' => '90020254321']);

    expect(hrdIdNumberFor($client, ['pesel_field' => 'PESEL']))->toBe('90020254321');
});

it('identifies a company by its NIP', function () {
    $client = Client::factory()->create(['client_type' => 'company', 'company_name' => 'Acme sp. z o.o.', 'tax_id' => '1234567890']);

    expect(hrdIdNumberFor($client))->toBe('1234567890');
});
