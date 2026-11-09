<?php

use App\Models\Admin;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTranslation;

/*
 * A product description in more than one language.
 *
 * The product's own description is the default language's text; a translation
 * row holds another active language's, and a visitor without one in their
 * language reads the default text.
 */

function productTranslationLanguages(): void
{
    Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::updateOrCreate(['code' => 'pl'], ['name' => 'Polish', 'native_name' => 'Polski', 'is_active' => true, 'is_default' => false]);
    Language::updateOrCreate(['code' => 'de'], ['name' => 'German', 'native_name' => 'Deutsch', 'is_active' => false, 'is_default' => false]);
}

function productWithDescription(): Product
{
    $currency = Currency::firstOrCreate(
        ['is_default' => true],
        ['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1.0, 'is_default' => true],
    );
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false,
        'retired' => false,
        'description' => 'Default description',
    ]);
    Pricing::factory()->create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => 9.99, 'quarterly' => -1, 'semiannually' => -1,
        'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    return $product;
}

test('a visitor reads the product description in their language', function () {
    productTranslationLanguages();
    $product = productWithDescription();
    ProductTranslation::create(['product_id' => $product->id, 'language' => 'pl', 'description' => 'Polski opis']);

    $this->get(route('client.store').'?lang=pl')->assertOk()
        ->assertSee('Polski opis')->assertDontSee('Default description');

    $this->get(route('client.store').'?lang=en')->assertOk()
        ->assertSee('Default description')->assertDontSee('Polski opis');
});

test('without a translation the product text is the default', function () {
    productTranslationLanguages();
    $product = Product::factory()->create(['description' => 'Default']);
    ProductTranslation::create(['product_id' => $product->id, 'language' => 'pl', 'description' => 'Polski']);

    app()->setLocale('pl');
    expect($product->localizedDescription())->toBe('Polski')
        ->and($product->descriptionHtml())->toContain('Polski');

    app()->setLocale('de');
    expect($product->localizedDescription())->toBe('Default');
});

test('the admin saves, changes and removes a translation', function () {
    productTranslationLanguages();
    $product = Product::factory()->create(['type' => 'hosting', 'description' => 'Base']);
    $admin = Admin::factory()->create();
    $base = ['name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring'];

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $base + [
        'translations' => ['pl' => ['description' => 'Polski opis']],
    ])->assertRedirect();
    expect(ProductTranslation::where('product_id', $product->id)->where('language', 'pl')->value('description'))->toBe('Polski opis');

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $base + [
        'translations' => ['pl' => ['description' => '']],
    ])->assertRedirect();
    expect(ProductTranslation::where('product_id', $product->id)->count())->toBe(0);
});

test('a translation for the default or an inactive language is not stored', function () {
    productTranslationLanguages();
    $product = Product::factory()->create(['type' => 'hosting']);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'translations' => [
            'pl' => ['description' => 'Polski'],
            'en' => ['description' => 'Dup'],
            'de' => ['description' => 'Nein'],
        ],
    ])->assertRedirect();

    expect(ProductTranslation::where('product_id', $product->id)->pluck('language')->all())->toBe(['pl']);
});

test('removing a product removes its translations', function () {
    productTranslationLanguages();
    $product = Product::factory()->create();
    ProductTranslation::create(['product_id' => $product->id, 'language' => 'pl', 'description' => 'x']);

    $product->forceDelete();

    expect(ProductTranslation::count())->toBe(0);
});

test('the product form offers the other languages as tabs', function () {
    productTranslationLanguages();
    $product = Product::factory()->create(['description' => 'Base']);
    ProductTranslation::create(['product_id' => $product->id, 'language' => 'pl', 'description' => 'Polski']);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))->assertOk()
        ->assertSee('translations[pl][description]', false)
        ->assertSee('md-desc-pl', false)
        ->assertSee(__('admin.products.description_copy_default'))
        ->assertSee(__('admin.content_translations.translated'))
        ->assertDontSee('translations[de][description]', false);

    $this->actingAs($admin, 'admin')->get(route('admin.products.create'))->assertOk()
        ->assertSee('translations[pl][description]', false)
        ->assertSee('md-desc-default', false);
});
