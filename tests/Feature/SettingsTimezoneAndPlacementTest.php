<?php

use App\Models\Admin;
use App\Models\Setting;

it('renders a grouped timezone selector with the current zone selected', function () {
    Setting::set('Timezone', 'Europe/Warsaw', 'general');

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('name="Timezone"', false)
        ->assertSee('<optgroup label="Europe">', false)
        ->assertSee('Europe/Warsaw')
        ->assertSee('America/New_York')
        ->assertSee('UTC')
        ->getContent();

    expect($html)->toContain('<option value="Europe/Warsaw" selected>');
});

it('keeps domain search logging in the domains tab and Turkish forms in the legal card', function () {
    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    $domainCard = strpos($html, 'id="settings-domain_search_log"');
    $domainInput = strpos($html, 'name="DomainSearchLog"');
    $registrarCard = strpos($html, 'id="settings-registrar_balance"');

    expect($domainCard)->not->toBeFalse()
        ->and($domainInput)->toBeGreaterThan($domainCard)
        ->and($domainInput)->toBeLessThan($registrarCard);

    $legalCard = strpos($html, 'id="settings-legal"');
    $turkishInput = strpos($html, 'name="TurkishLegalForms"');
    $automationCard = strpos($html, 'id="settings-automation"');

    expect($legalCard)->not->toBeFalse()
        ->and($turkishInput)->toBeGreaterThan($legalCard)
        ->and($turkishInput)->toBeLessThan($automationCard);
});
