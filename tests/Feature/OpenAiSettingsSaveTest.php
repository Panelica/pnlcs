<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * The AI translation settings could never be saved.
 *
 * The languages screen posted its two fields as settings[OpenAIApiKey] and
 * settings[OpenAIModel] to the general-settings endpoint, which reads only
 * top-level whitelisted fields - the nested array was dropped without a word.
 * Save reported success, the database gained nothing, and AI translation was
 * unconfigurable through the UI. Verified on a live install: zero OpenAI rows.
 */

function aiSettingsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the api key and model can actually be saved', function () {
    $this->actingAs(aiSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'OpenAIApiKey' => 'sk-test-abc123',
            'OpenAIModel'  => 'gpt-4o',
        ])
        ->assertRedirect();

    expect(Setting::get('OpenAIApiKey'))->toBe('sk-test-abc123')
        ->and(Setting::get('OpenAIModel'))->toBe('gpt-4o');
});

test('a blank key field keeps the stored key', function () {
    Setting::set('OpenAIApiKey', 'sk-existing', 'general');

    // Same contract as the SMTP password: the secret is not echoed into the
    // form, so an untouched field comes back empty - that must mean "keep".
    $this->actingAs(aiSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'OpenAIApiKey' => '',
            'OpenAIModel'  => 'gpt-4o-mini',
        ])
        ->assertRedirect();

    expect(Setting::get('OpenAIApiKey'))->toBe('sk-existing');
});

test('the form no longer uses the field shape the handler cannot read', function () {
    $markup = file_get_contents(resource_path('views/admin/config/languages/index.blade.php'));

    expect($markup)->not->toContain('settings[OpenAI')
        ->and($markup)->toContain('name="OpenAIApiKey"')
        ->and($markup)->toContain('name="OpenAIModel"');
});

test('the stored key is not echoed back into the page', function () {
    Setting::set('OpenAIApiKey', 'sk-very-secret', 'general');

    $html = $this->actingAs(aiSettingsAdmin(), 'admin')
        ->get(route('admin.config.languages.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('sk-very-secret');
});
