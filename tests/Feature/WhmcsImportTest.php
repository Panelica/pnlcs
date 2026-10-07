<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\WhmcsImport\ClientImporter;
use App\Services\WhmcsImport\DomainImporter;
use App\Services\WhmcsImport\ImportValidator;
use App\Services\WhmcsImport\MappingEngine;
use App\Services\WhmcsImport\SchemaReader;
use App\Services\WhmcsImport\ServiceImporter;
use Illuminate\Support\Facades\Mail;

function whmcsImportAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the mapper suggests PNLCS fields for WHMCS columns', function () {
    $engine = new MappingEngine;

    expect($engine->suggest('firstname'))->toBe('first_name')
        ->and($engine->suggest('companyname'))->toBe('company_name')
        ->and($engine->suggest('phonenumber'))->toBe('phone_number')
        ->and($engine->suggest('tax_id'))->toBe('tax_id')
        ->and($engine->suggest('whatever'))->toBeNull();
});

test('the mapper copies a single column', function () {
    $engine = new MappingEngine;

    $target = $engine->apply(
        ['email' => 'jan@example.com'],
        ['columns' => ['email' => 'email'], 'constants' => []],
    );

    expect($target)->toBe(['email' => 'jan@example.com']);
});

test('the mapper applies constants and fills a missing column', function () {
    $engine = new MappingEngine;

    $target = $engine->apply(
        ['firstname' => 'Jan', 'lastname' => 'Kowalski'],
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name'],
            'constants' => ['country' => 'PL'],
        ],
    );

    expect($target['country'])->toBe('PL')
        ->and($target['first_name'])->toBe('Jan');
});

test('the mapper joins several source columns into one target', function () {
    $engine = new MappingEngine;

    $target = $engine->apply(
        ['firstname' => 'Jan', 'lastname' => 'Kowalski'],
        ['columns' => ['firstname' => 'company_name', 'lastname' => 'company_name'], 'constants' => []],
    );

    expect($target['company_name'])->toBe('Jan Kowalski');
});

test('the mapper normalizes WHMCS status words', function () {
    $engine = new MappingEngine;

    expect($engine->apply(['status' => 'Active'], ['columns' => ['status' => 'status'], 'constants' => []])['status'])->toBe('active')
        ->and($engine->apply(['status' => 'Inactive'], ['columns' => ['status' => 'status'], 'constants' => []])['status'])->toBe('inactive')
        ->and($engine->apply(['status' => 'Closed'], ['columns' => ['status' => 'status'], 'constants' => []])['status'])->toBe('closed');
});

test('the mapper applies a regex transform to the final value', function () {
    $engine = new MappingEngine;

    $target = $engine->apply(
        ['tax_id' => 'PL6482409327'],
        [
            'columns' => ['tax_id' => 'tax_id'],
            'constants' => [],
            'transforms' => ['tax_id' => ['pattern' => '/[^0-9]/', 'replacement' => '']],
        ],
    );

    expect($target['tax_id'])->toBe('6482409327');
});

test('validation rejects a broken regex transform', function () {
    $validator = new ImportValidator;

    $errors = $validator->mapping(
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'],
            'constants' => [],
            'transforms' => ['tax_id' => ['pattern' => '/[invalid/', 'replacement' => '']],
        ],
        ['first_name', 'last_name', 'email', 'tax_id'],
        ['firstname', 'lastname', 'email'],
        'email',
        'add',
    );

    expect(implode("\n", $errors))->toContain('tax_id');
});

test('validation demands the non-null fields for add mode', function () {
    $validator = new ImportValidator;

    $errors = $validator->mapping(
        ['columns' => ['email' => 'email'], 'constants' => []],
        ['first_name', 'last_name', 'email'],
        ['email'],
        null,
        'add',
    );

    expect(implode("\n", $errors))->toContain('first_name')->toContain('last_name');
});

test('validation rejects a target field that is not on the model', function () {
    $validator = new ImportValidator;

    $errors = $validator->mapping(
        ['columns' => ['firstname' => 'bogus'], 'constants' => []],
        ['first_name', 'last_name', 'email'],
        ['firstname'],
        null,
        'add',
    );

    expect(implode("\n", $errors))->toContain('bogus');
});

test('validation wants a match key for update modes', function () {
    $validator = new ImportValidator;

    $errors = $validator->mapping(
        ['columns' => ['firstname' => 'first_name'], 'constants' => []],
        ['first_name', 'last_name', 'email'],
        ['firstname'],
        null,
        'update',
    );

    expect($errors)->not->toBeEmpty();
});

test('the client importer adds new rows', function () {
    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        function ($cb) {
            foreach ([
                ['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Kowalski', 'email' => 'jan@example.com'],
                ['id' => 2, 'firstname' => 'Anna', 'lastname' => 'Nowak', 'email' => 'anna@example.com'],
            ] as $row) {
                $cb($row);
            }
        },
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'],
            'constants' => ['country' => 'PL'],
        ],
        'add',
        'email',
    );

    expect($summary['total'])->toBe(2)
        ->and($summary['added'])->toBe(2)
        ->and($summary['updated'])->toBe(0)
        ->and($summary['errors'])->toBe(0);

    $client = Client::where('email', 'jan@example.com')->first();
    expect($client)->not->toBeNull()
        ->and($client->first_name)->toBe('Jan')
        ->and($client->country)->toBe('PL');
});

test('the client importer updates existing rows in add_update mode', function () {
    Client::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'jan@example.com',
    ]);

    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'New', 'lastname' => 'Name', 'email' => 'jan@example.com']),
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'],
            'constants' => [],
        ],
        'add_update',
        'email',
    );

    expect($summary['added'])->toBe(0)
        ->and($summary['updated'])->toBe(1);

    expect(Client::where('email', 'jan@example.com')->first()->first_name)->toBe('New');
});

test('the client importer skips new rows in update mode', function () {
    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'New', 'lastname' => 'Name', 'email' => 'nobody@example.com']),
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'],
            'constants' => [],
        ],
        'update',
        'email',
    );

    expect($summary['added'])->toBe(0)
        ->and($summary['updated'])->toBe(0)
        ->and($summary['skipped'])->toBe(1);

    expect(Client::where('email', 'nobody@example.com')->exists())->toBeFalse();
});

test('the client importer records a missing required field as an error', function () {
    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Kowalski', 'email' => '']),
        [
            'columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'],
            'constants' => [],
        ],
        'add',
        'email',
    );

    expect($summary['errors'])->toBe(1)
        ->and($summary['error_details'][0]['whmcs_id'])->toBe(1);
});

test('the schema reader excludes internal client fields', function () {
    $fields = (new SchemaReader)->clientTargetFields();

    expect($fields)->toContain('first_name', 'email', 'country')
        ->and($fields)->not->toContain('credit', 'auto_charge', 'affiliate_id');
});

test('the schema reader exposes PNLCS client custom fields as targets', function () {
    CustomField::create(['type' => 'client', 'field_name' => 'CSA', 'field_type' => 'text']);

    $fields = (new SchemaReader)->clientTargetFields();

    expect($fields)->toContain('custom_field:CSA');
});

test('the client importer writes mapped PNLCS custom field values', function () {
    CustomField::create(['type' => 'client', 'field_name' => 'CSA', 'field_type' => 'text']);

    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Kowalski', 'email' => 'jan@example.com', 'custom:CSA' => 'csa123']),
        [
            'columns' => [
                'firstname' => 'first_name',
                'lastname' => 'last_name',
                'email' => 'email',
                'custom:CSA' => 'custom_field:CSA',
            ],
            'constants' => [],
        ],
        'add',
        'email',
    );

    expect($summary['errors'])->toBe(0);

    $client = Client::where('email', 'jan@example.com')->first();
    expect($client)->not->toBeNull();

    $value = CustomFieldValue::where('rel_id', $client->id)->first();
    expect($value)->not->toBeNull()
        ->and($value->value)->toBe('csa123');
});

test('the schema reader exposes PNLCS domain fields as targets', function () {
    $fields = (new SchemaReader)->domainTargetFields();

    expect($fields)->toContain('domain', 'registrar', 'expiry_date', 'status')
        ->and($fields)->not->toContain('client_id', 'epp_code');
});

test('the mapper normalizes WHMCS domain status words', function () {
    $engine = new MappingEngine;

    expect($engine->apply(['status' => 'Active'], ['columns' => ['status' => 'status']])['status'])->toBe('active')
        ->and($engine->apply(['status' => 'Pending Transfer'], ['columns' => ['status' => 'status']])['status'])->toBe('pending')
        ->and($engine->apply(['status' => 'Transferred Away'], ['columns' => ['status' => 'status']])['status'])->toBe('transferred_away');
});

test('the domain importer links the domain to the client matched by email', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);

    $importer = app(DomainImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'userid' => 5, 'domain' => 'example.com', 'status' => 'Active', 'client_email' => 'owner@example.com']),
        ['columns' => ['domain' => 'domain', 'status' => 'status'], 'constants' => []],
        'add',
        'domain',
    );

    expect($summary['added'])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    $domain = Domain::where('domain', 'example.com')->first();
    expect($domain)->not->toBeNull()
        ->and($domain->client_id)->toBe($client->id)
        ->and($domain->status)->toBe('active');
});

test('the domain importer skips a domain whose owner email is unknown', function () {
    $importer = app(DomainImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'userid' => 5, 'domain' => 'example.com', 'client_email' => 'ghost@example.com']),
        ['columns' => ['domain' => 'domain'], 'constants' => []],
        'add',
        'domain',
    );

    expect($summary['added'])->toBe(0)
        ->and($summary['errors'])->toBe(1)
        ->and($summary['error_details'][0]['error'])->toContain('ghost@example.com');
});

test('the schema reader exposes PNLCS service fields as targets', function () {
    $fields = (new SchemaReader)->serviceTargetFields();

    expect($fields)->toContain('domain', 'username', 'billing_cycle', 'amount')
        ->and($fields)->not->toContain('client_id', 'product_id', 'server_id', 'password');
});

test('the mapper normalizes WHMCS billing cycles and service statuses', function () {
    $engine = new MappingEngine;

    expect($engine->apply(['billingcycle' => 'Annually'], ['columns' => ['billingcycle' => 'billing_cycle']])['billing_cycle'])->toBe('annually')
        ->and($engine->apply(['billingcycle' => 'Semi-Annually'], ['columns' => ['billingcycle' => 'billing_cycle']])['billing_cycle'])->toBe('semiannually')
        ->and($engine->apply(['domainstatus' => 'Suspended'], ['columns' => ['domainstatus' => 'status']])['status'])->toBe('suspended')
        ->and($engine->apply(['domainstatus' => 'Terminated'], ['columns' => ['domainstatus' => 'status']])['status'])->toBe('terminated');
});

test('the service importer resolves client, product and server and imports', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $product = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Pro']);
    $server = Server::factory()->create(['name' => 'HestiaCP 00']);

    $importer = app(ServiceImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb([
            'id' => 21, 'userid' => 12, 'packageid' => 17, 'server' => 6,
            'domain' => 'sektorsztuki.pl', 'domainstatus' => 'Active', 'billingcycle' => 'Annually',
            'client_email' => 'owner@example.com', 'product_name' => 'Hosting Pro', 'server_name' => 'HestiaCP 00',
        ]),
        ['columns' => ['domain' => 'domain', 'domainstatus' => 'status', 'billingcycle' => 'billing_cycle'], 'constants' => []],
        'add',
        'domain',
    );

    expect($summary['added'])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    $service = Service::where('domain', 'sektorsztuki.pl')->first();
    expect($service)->not->toBeNull()
        ->and($service->client_id)->toBe($client->id)
        ->and($service->product_id)->toBe($product->id)
        ->and($service->server_id)->toBe($server->id)
        ->and($service->status)->toBe('active')
        ->and($service->billing_cycle)->toBe('annually');
});

test('the service importer skips a service whose client email is unknown', function () {
    $importer = app(ServiceImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'userid' => 5, 'domain' => 'x.pl', 'client_email' => 'ghost@example.com']),
        ['columns' => ['domain' => 'domain'], 'constants' => []],
        'add',
        'domain',
    );

    expect($summary['added'])->toBe(0)
        ->and($summary['errors'])->toBe(1);
});

test('the client importer marks a client with a NIP as a company', function () {
    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Kowalski', 'email' => 'jan@example.com', 'tax_id' => '6482409327']),
        [
            'columns' => [
                'firstname' => 'first_name',
                'lastname' => 'last_name',
                'email' => 'email',
                'tax_id' => 'tax_id',
            ],
            'constants' => [],
        ],
        'add',
        'email',
    );

    expect($summary['errors'])->toBe(0);

    $client = Client::where('email', 'jan@example.com')->first();
    expect($client)->not->toBeNull()
        ->and($client->client_type)->toBe('company');
});

test('the client importer leaves a client without a NIP as an individual', function () {
    $importer = app(ClientImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Kowalski', 'email' => 'jan@example.com', 'tax_id' => '']),
        [
            'columns' => [
                'firstname' => 'first_name',
                'lastname' => 'last_name',
                'email' => 'email',
                'tax_id' => 'tax_id',
            ],
            'constants' => [],
        ],
        'add',
        'email',
    );

    expect($summary['errors'])->toBe(0);

    $client = Client::where('email', 'jan@example.com')->first();
    expect($client)->not->toBeNull()
        ->and($client->client_type)->toBeNull();
});

test('the whmcs import index page is behind manage_settings', function () {
    $this->actingAs(whmcsImportAdmin(), 'admin')
        ->get(route('admin.whmcs-import.index'))
        ->assertOk()
        ->assertSee(__('whmcs_import.title'), false);
});

test('updating never moves a domain to another client', function () {
    $alice = Client::factory()->create(['email' => 'alice@example.com']);
    Client::factory()->create(['email' => 'bob@example.com']);
    Domain::create(['client_id' => $alice->id, 'domain' => 'shop.example.com', 'type' => 'register', 'status' => 'active']);

    $summary = app(DomainImporter::class)->run(
        fn ($cb) => $cb(['id' => 9, 'userid' => 3, 'domain' => 'shop.example.com', 'status' => 'Active', 'client_email' => 'bob@example.com']),
        ['columns' => ['domain' => 'domain', 'status' => 'status'], 'constants' => []],
        'add_update',
        'domain',
    );

    expect(Domain::where('domain', 'shop.example.com')->sole()->client_id)->toBe($alice->id)
        ->and($summary['skipped'])->toBe(1)
        ->and($summary['skipped_details'][0]['error'])->toBe(__('whmcs_import.log.skip_other_client'));
});

test('updating never moves a service, nor its product or server', function () {
    $alice = Client::factory()->create(['email' => 'alice@example.com']);
    $product = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Pro']);
    $other = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Max']);
    $service = Service::factory()->create([
        'client_id' => $alice->id,
        'product_id' => $product->id,
        'domain' => 'alice.example.com',
        'billing_cycle' => 'monthly',
        'whmcs_product_name' => 'Hosting Pro',
    ]);
    Client::factory()->create(['email' => 'bob@example.com']);

    $row = ['id' => 3, 'packageid' => 17, 'domain' => 'alice.example.com', 'billingcycle' => 'Annually', 'product_name' => 'Hosting Pro'];
    $mapping = ['columns' => ['domain' => 'domain', 'billingcycle' => 'billing_cycle'], 'constants' => []];

    // Another client's row with the same domain is skipped.
    $skipped = app(ServiceImporter::class)->run(fn ($cb) => $cb($row + ['client_email' => 'bob@example.com']), $mapping, 'add_update', 'domain');
    // The owner's row updates the record but not what it runs on — even when a
    // saved mapping would point the product elsewhere.
    $updated = app(ServiceImporter::class)->run(fn ($cb) => $cb($row + ['client_email' => 'alice@example.com']), $mapping, 'add_update', 'domain', ['17' => $other->id]);

    $service->refresh();
    expect($skipped['skipped'])->toBe(1)
        ->and($updated['updated'])->toBe(1)
        ->and($service->client_id)->toBe($alice->id)
        ->and($service->product_id)->toBe($product->id)
        ->and($service->billing_cycle)->toBe('annually')
        ->and($other->id)->not->toBe($service->product_id);
});

test('two services on one domain stay apart, mapped or not', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $hosting = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Pro']);
    $email = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Email']);

    $importer = app(ServiceImporter::class);
    $mapping = ['columns' => ['domain' => 'domain'], 'constants' => []];

    // Unmapped: no PNLCS product carries either source name.
    $unmapped = $importer->run(function ($cb) {
        $cb(['id' => 1, 'packageid' => 11, 'domain' => 'a.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'VPS maintenance']);
        $cb(['id' => 2, 'packageid' => 12, 'domain' => 'a.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'VPS individual']);
    }, $mapping, 'add', 'domain');

    expect($unmapped['added'])->toBe(2)
        ->and(Service::where('domain', 'a.example.com')->count())->toBe(2);

    // Mapped: the WHMCS product name still keeps them apart.
    $mapped = $importer->run(function ($cb) {
        $cb(['id' => 3, 'packageid' => 21, 'domain' => 'b.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'Whatever']);
        $cb(['id' => 4, 'packageid' => 22, 'domain' => 'b.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'Something else']);
    }, $mapping, 'add', 'domain', ['21' => $hosting->id, '22' => $email->id]);

    expect($mapped['added'])->toBe(2)
        ->and(Service::where('domain', 'b.example.com')->count())->toBe(2)
        ->and(Service::where('domain', 'b.example.com')->where('product_id', $hosting->id)->count())->toBe(1)
        ->and(Service::where('domain', 'b.example.com')->where('product_id', $email->id)->count())->toBe(1);
});

test('a service imported before the product name was recorded is updated on a re-import, not copied', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $hosting = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Pro']);
    // As the 1.4.0 importer left it: product found by name, no WHMCS product name.
    $old = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $hosting->id, 'domain' => 'a.example.com',
        'amount' => 50, 'billing_cycle' => 'monthly', 'whmcs_product_name' => null,
    ]);

    $importer = app(ServiceImporter::class);
    $mapping = ['columns' => ['domain' => 'domain', 'amount' => 'amount', 'billingcycle' => 'billing_cycle'], 'constants' => []];
    $row = ['id' => 1, 'packageid' => 11, 'domain' => 'a.example.com', 'amount' => '60.00', 'billingcycle' => 'Annually', 'client_email' => 'owner@example.com', 'product_name' => 'Hosting Pro'];

    $first = $importer->run(fn ($cb) => $cb($row), $mapping, 'add_update', 'domain');
    $second = $importer->run(fn ($cb) => $cb($row), $mapping, 'add_update', 'domain');

    $old->refresh();
    expect($first['updated'])->toBe(1)->and($first['added'])->toBe(0)
        ->and($second['updated'])->toBe(1)->and($second['added'])->toBe(0)
        ->and(Service::where('domain', 'a.example.com')->count())->toBe(1)
        ->and((float) $old->amount)->toBe(60.0)
        ->and($old->billing_cycle)->toBe('annually')
        // Recorded now, so the next run matches it by name.
        ->and($old->whmcs_product_name)->toBe('Hosting Pro');
});

test('a service imported before the product name was recorded is never taken by another product', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $hosting = Product::factory()->create(['type' => 'hostingaccount', 'name' => 'Hosting Pro']);
    $old = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $hosting->id, 'domain' => 'a.example.com',
        'amount' => 50, 'billing_cycle' => 'monthly', 'whmcs_product_name' => null,
    ]);

    // An e-mail service on the same domain: no PNLCS product of that name.
    $summary = app(ServiceImporter::class)->run(
        fn ($cb) => $cb(['id' => 2, 'packageid' => 12, 'domain' => 'a.example.com', 'amount' => '5.00', 'client_email' => 'owner@example.com', 'product_name' => 'Email']),
        ['columns' => ['domain' => 'domain', 'amount' => 'amount'], 'constants' => []],
        'add_update', 'domain',
    );

    $old->refresh();
    expect($summary['added'])->toBe(1)->and($summary['updated'])->toBe(0)
        ->and(Service::where('domain', 'a.example.com')->count())->toBe(2)
        ->and((float) $old->amount)->toBe(50.0)
        ->and($old->whmcs_product_name)->toBeNull();
});

test('a mapped product id that does not exist is reported and left unlinked', function () {
    Client::factory()->create(['email' => 'owner@example.com']);

    $importer = app(ServiceImporter::class);
    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'packageid' => 99, 'domain' => 'a.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'Gone']),
        ['columns' => ['domain' => 'domain'], 'constants' => []],
        'add', 'domain', ['99' => 999999],
    );

    expect($summary['added'])->toBe(1)
        ->and(Service::where('domain', 'a.example.com')->first()->product_id)->toBeNull()
        ->and(collect($summary['skipped_details'])->pluck('error')->implode(' '))->toContain('999999');
});

test('the source-services note is written once, not duplicated on a re-run', function () {
    $client = Client::factory()->create(['email' => 'owner@example.com']);

    $importer = app(ServiceImporter::class);
    $row = fn ($cb) => $cb(['id' => 1, 'packageid' => 5, 'domain' => 'a.example.com', 'client_email' => 'owner@example.com', 'product_name' => 'Hosting']);

    $importer->run($row, ['columns' => ['domain' => 'domain'], 'constants' => []], 'add', 'domain');
    $importer->run($row, ['columns' => ['domain' => 'domain'], 'constants' => []], 'add', 'domain');

    expect(ClientNote::where('client_id', $client->id)->count())->toBe(1);
});

test('importing clients sends no mail; their logins are ready for "Forgot password"', function () {
    Mail::fake();

    app(ClientImporter::class)->run(
        function ($cb) {
            foreach (range(1, 3) as $i) {
                $cb(['id' => $i, 'firstname' => 'C'.$i, 'lastname' => 'X', 'email' => "c{$i}@example.com"]);
            }
        },
        ['columns' => ['firstname' => 'first_name', 'lastname' => 'last_name', 'email' => 'email'], 'constants' => []],
        'add',
        'email',
    );

    Mail::assertNothingSent();
    expect(User::whereIn('email', ['c1@example.com', 'c2@example.com', 'c3@example.com'])->count())->toBe(3);
});

test('a connection host or database name cannot carry extra connection parameters', function (string $field, string $value) {
    $this->actingAs(whmcsImportAdmin(), 'admin')
        ->post(route('admin.whmcs-import.connection.store'), ['host' => 'db.example.com', 'port' => 3306, 'database' => 'whmcs', 'username' => 'u', $field => $value])
        ->assertSessionHasErrors($field);
})->with([
    'host' => ['host', 'db.example.com;unix_socket=/var/run/mysqld/mysqld.sock'],
    'database' => ['database', 'whmcs;host=10.0.0.1'],
]);
