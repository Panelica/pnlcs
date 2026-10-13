<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;

/*
 * My Account offers only the active languages (Language::active()), while the
 * default language on Setup -> Languages also offers a fully translated one
 * that is switched off. An administrator who saw German at 100% there and not
 * here had no way to tell why; the page now says so, in their language, and
 * links to the place where a language is switched on.
 */
function hintLanguage(string $code, string $native, bool $active, int $order): void
{
    Language::updateOrCreate(['code' => $code], [
        'name' => $native,
        'native_name' => $native,
        'flag_code' => $code,
        'direction' => 'ltr',
        'is_active' => $active,
        'is_default' => $code === 'en',
        'sort_order' => $order,
        'translation_progress' => 100,
    ]);
}

function hintAdmin(array $role): Admin
{
    hintLanguage('en', 'English', true, 1);
    hintLanguage('es', 'Español', true, 2);
    hintLanguage('de', 'Deutsch', false, 3);

    return Admin::factory()->create(['language' => 'en', 'role_id' => AdminRole::factory()->create($role)->id]);
}

test('my account lists the active languages and says only those are offered, linking to them', function () {
    $page = $this->actingAs(hintAdmin(['is_full_admin' => true]), 'admin')
        ->get(route('admin.my-account'))
        ->assertOk();

    $page->assertSee('<option value="es"', false)
        ->assertDontSee('<option value="de"', false)
        ->assertSee('class="language-active-hint" style="color:#999;display:block;margin-top:4px;">Only the languages active under <a href="'
            .route('admin.config.languages.index').'">Setup → Languages</a> are listed here.</small>', false);
});

test('an administrator who may not open the language settings gets the path without a link', function () {
    $this->actingAs(hintAdmin(['permissions' => ['list_clients']]), 'admin')
        ->get(route('admin.my-account'))
        ->assertOk()
        ->assertSee('class="language-active-hint" style="color:#999;display:block;margin-top:4px;">Only the languages active under Setup → Languages are listed here.</small>', false);
});

test('the hint is in the administrator\'s language, menu names included', function () {
    $admin = hintAdmin(['is_full_admin' => true]);
    app()->setLocale('es');

    $this->actingAs($admin, 'admin')
        ->withSession(['locale' => 'es'])
        ->get(route('admin.my-account'))
        ->assertOk()
        ->assertSee('Aquí solo aparecen los idiomas activos en <a href="'
            .route('admin.config.languages.index').'">'.__('admin.nav.setup', [], 'es').' → '.__('admin.nav.languages', [], 'es').'</a>.', false);
});
