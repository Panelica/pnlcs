<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\RegistrarSettings;
use App\Models\Setting;
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

it('writes the prices with the margin onto its own TLDs only', function () {
    zlotyShop();
    $pl = hrdTld('.pl');
    $comPl = hrdTld('.com.pl');
    $other = hrdTld('.de', 'openprovider');

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS), ['margin_percent' => '20'])->syncPrices();

    expect($result['success'])->toBeTrue()
        ->and($result['updated'])->toBe(2)
        // 46 * 1.20 = 55.2.
        ->and((float) $pl->fresh()->register_price)->toBe(55.2)
        ->and((float) $pl->fresh()->restore_price)->toBe(66.0)
        // A price HRD does not charge stays at zero, not at the old number.
        ->and((float) $pl->fresh()->transfer_price)->toBe(0.0)
        // HRD publishes no renewal price for .com.pl: the operator's own
        // number stays rather than becoming zero.
        ->and((float) $comPl->fresh()->renew_price)->toBe(1.0)
        ->and((float) $comPl->fresh()->register_price)->toBe(12.6)
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

it('converts złoty prices into the shop currency', function () {
    zlotyShop('USD');
    Currency::create(['code' => 'PLN', 'name' => 'PLN', 'prefix' => '', 'suffix' => ' PLN', 'rate' => 2.0, 'is_default' => false]);
    $pl = hrdTld('.pl');

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS), ['margin_percent' => '20'])->syncPrices();

    // 46 PLN @ 2 PLN/USD = 23 USD; +20% → 27.6.
    expect($result['success'])->toBeTrue()
        ->and((float) $pl->fresh()->register_price)->toBe(27.6);
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

it('names the registrar being synced, not HRD', function () {
    // #382. The confirmation and the result said "HRD" whichever registrar's
    // button was pressed, which is wrong the moment a second one can sync.
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'visible'], ['value' => '1']);
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Config', 'permissions' => ['manage_registrars']])->id,
    ]);

    $html = test()->actingAs($admin, 'admin')->get(route('admin.config.registrars'))->assertOk()->getContent();

    // Two syncable registrars, each button's confirmation naming its own:
    // before #382 both said HRD. ("prices from HRD" also appears in HRD's own
    // help text, which is correct there, so the whole sentence is counted.)
    expect(substr_count($html, 'Fetch prices from OVH and overwrite'))->toBe(1)
        ->and(substr_count($html, 'Fetch prices from HRD and overwrite'))->toBe(1);

    // And the message the sync itself returns.
    expect(__('admin.registrars.sync_success', ['name' => 'OVH', 'updated' => 1, 'unchanged' => 2, 'skipped' => 3]))
        ->toBe('Prices synced: 1 changed, 2 unchanged, 3 skipped (no price in OVH).');
});

const HRD_TLD_PERIODS = <<<'XML'
<api>
  <partner><pricings>
    <pricing><name>d_de</name><price><name>Rejestracja</name><value>5.00</value></price></pricing>
    <pricing><name>d_tk</name><price><name>Rejestracja</name><value>10.00</value></price></pricing>
    <pricing><name>d_tm</name><price><name>Rejestracja</name><value>50.00</value></price></pricing>
  </pricings></partner>
</api>
XML;

it('imports the HRD TLDs without duplicating existing extensions', function () {
    // "local" is the operator's own country, so the test says which it is
    // rather than leaning on a default.
    Setting::set('Country', 'PL', 'general');
    $existing = hrdTld('.pl', 'openprovider');

    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS))->importTlds();

    expect($result)->toMatchArray(['success' => true, 'added' => 1, 'skipped' => 1]);

    $comPl = DomainPricing::where('extension', '.com.pl')->first();
    expect($comPl->auto_registrar)->toBe('hrd')
        ->and($comPl->min_years)->toBe(1)
        ->and($comPl->max_years)->toBe(10)
        ->and($comPl->grace_period)->toBe(0)          // NASK .pl: no renew grace
        ->and($comPl->redemption_grace_period)->toBe(30)
        ->and($comPl->category)->toBe('local')        // the operator's own country TLD
        // Off until it is priced: see "imports TLDs switched off" below.
        ->and((bool) $comPl->enabled)->toBeFalse();

    // Already present on another registrar: left alone, not switched to HRD.
    expect($existing->fresh()->auto_registrar)->toBe('openprovider');
});

it('applies the registration period limits from HRD\'s extension list', function () {
    $result = hrdRegistrarWith(hrdPricings(HRD_TLD_PERIODS))->importTlds();

    expect($result['added'])->toBe(3)
        ->and(DomainPricing::where('extension', '.de')->value('max_years'))->toBe(1)
        ->and(DomainPricing::where('extension', '.tk')->value('min_years'))->toBe(2)
        ->and(DomainPricing::where('extension', '.tk')->value('max_years'))->toBe(9)
        ->and(DomainPricing::where('extension', '.tm')->value('min_years'))->toBe(10)
        ->and(DomainPricing::where('extension', '.tm')->value('max_years'))->toBe(10);
});

const HRD_GRACE_PERIODS = <<<'XML'
<api>
  <partner><pricings>
    <pricing><name>d_com</name><price><name>Rejestracja</name><value>10.00</value></price></pricing>
    <pricing><name>d_pl</name><price><name>Rejestracja</name><value>40.00</value></price></pricing>
    <pricing><name>d_de</name><price><name>Rejestracja</name><value>5.00</value></price></pricing>
    <pricing><name>d_eu</name><price><name>Rejestracja</name><value>20.00</value></price></pricing>
  </pricings></partner>
</api>
XML;

it('applies the grace and redemption periods the registries actually grant', function () {
    hrdRegistrarWith(hrdPricings(HRD_GRACE_PERIODS))->importTlds();

    $g = fn ($e) => DomainPricing::where('extension', $e)->value('grace_period');
    $r = fn ($e) => DomainPricing::where('extension', $e)->value('redemption_grace_period');

    // ICANN gTLD: 5 days renew grace, 30 redemption.
    expect([$g('.com'), $r('.com')])->toBe([5, 30])
        // NASK .pl: no grace, 30-day quarantine.
        ->and([$g('.pl'), $r('.pl')])->toBe([0, 30])
        // DENIC .de: no grace, no redemption.
        ->and([$g('.de'), $r('.de')])->toBe([0, 0])
        // EURid .eu: no grace, 30-day quarantine.
        ->and([$g('.eu'), $r('.eu')])->toBe([0, 30]);
});

const HRD_CATEGORIES = <<<'XML'
<api>
  <partner><pricings>
    <pricing><name>d_com</name><price><name>Rejestracja</name><value>10.00</value></price></pricing>
    <pricing><name>d_pl</name><price><name>Rejestracja</name><value>40.00</value></price></pricing>
    <pricing><name>d_de</name><price><name>Rejestracja</name><value>5.00</value></price></pricing>
    <pricing><name>d_xyz</name><price><name>Rejestracja</name><value>15.00</value></price></pricing>
  </pricings></partner>
</api>
XML;

it('assigns the pricing category that matches each TLD', function () {
    Setting::set('Country', 'PL', 'general');
    hrdRegistrarWith(hrdPricings(HRD_CATEGORIES))->importTlds();

    $cat = fn ($e) => DomainPricing::where('extension', $e)->value('category');

    expect($cat('.com'))->toBe('generic')
        ->and($cat('.pl'))->toBe('local')
        ->and($cat('.de'))->toBe('country')
        ->and($cat('.xyz'))->toBe('new');
});

/*
 * An imported TLD is a draft, not a product.
 *
 * The import fills the extension, the registrar, the year range and the grace
 * periods; it does not and cannot know what to charge — "Sync prices" is a
 * separate step. Every price column defaults to 0, so an imported extension
 * that arrives enabled is on sale for nothing: the domain search lists
 * `enabled = true` rows and the cart accepts any enabled extension. The same
 * rule products already follow (#348: a copied product arrives hidden).
 */
it('imports TLDs switched off, so nothing is on sale at nothing', function () {
    $result = hrdRegistrarWith(hrdPricings(HRD_PRICINGS))->importTlds();

    expect($result['success'])->toBeTrue()
        ->and($result['added'])->toBeGreaterThan(0);

    $imported = DomainPricing::where('extension', '.pl')->firstOrFail();

    expect((bool) $imported->enabled)->toBeFalse()
        ->and((float) $imported->register_price)->toBe(0.0)
        // ...so the customer's domain search, which lists only enabled rows,
        // does not offer it before a price is set.
        ->and(DomainPricing::where('enabled', true)->pluck('extension')->all())->not->toContain('.pl');
});

it('leaves an extension that was already on sale switched on', function () {
    // The operator's own row, priced and enabled: an import must not touch it.
    $existing = hrdTld('.pl', 'hrd', ['register_price' => 50, 'enabled' => true]);

    hrdRegistrarWith(hrdPricings(HRD_PRICINGS))->importTlds();

    expect((bool) $existing->fresh()->enabled)->toBeTrue()
        ->and((float) $existing->fresh()->register_price)->toBe(50.0);
});

it('calls no extension local when the installation has not said which country it is in', function () {
    Setting::where('setting', 'Country')->delete();

    hrdRegistrarWith(hrdPricings(HRD_CATEGORIES))->importTlds();

    // .pl is then simply another country's extension, not "ours".
    expect(DomainPricing::where('extension', '.pl')->value('category'))->toBe('country');
});
