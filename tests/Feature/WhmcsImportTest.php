<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Services\WhmcsImport\ClientImporter;
use App\Services\WhmcsImport\ImportValidator;
use App\Services\WhmcsImport\MappingEngine;
use App\Services\WhmcsImport\SchemaReader;

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

    $importer = app(\App\Services\WhmcsImport\DomainImporter::class);

    $summary = $importer->run(
        fn ($cb) => $cb(['id' => 1, 'userid' => 5, 'domain' => 'example.com', 'status' => 'Active', 'client_email' => 'owner@example.com']),
        ['columns' => ['domain' => 'domain', 'status' => 'status'], 'constants' => []],
        'add',
        'domain',
    );

    expect($summary['added'])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    $domain = \App\Models\Domain::where('domain', 'example.com')->first();
    expect($domain)->not->toBeNull()
        ->and($domain->client_id)->toBe($client->id)
        ->and($domain->status->value)->toBe('active');
});

test('the domain importer skips a domain whose owner email is unknown', function () {
    $importer = app(\App\Services\WhmcsImport\DomainImporter::class);

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

test('the whmcs import index page is behind manage_settings', function () {
    $this->actingAs(whmcsImportAdmin(), 'admin')
        ->get(route('admin.whmcs-import.index'))
        ->assertOk()
        ->assertSee(__('whmcs_import.title'), false);
});
