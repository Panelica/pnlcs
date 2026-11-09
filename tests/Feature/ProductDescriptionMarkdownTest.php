<?php

use App\Models\Admin;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;

test('the product description renders Markdown as HTML', function () {
    $product = Product::factory()->create([
        'description' => "**Fast** and *safe*\n\n- one\n- two\n\n[site](https://example.com)",
    ]);

    $html = $product->descriptionHtml();

    expect($html)->toContain('<strong>Fast</strong>')
        ->and($html)->toContain('<em>safe</em>')
        ->and($html)->toContain('<li>one</li>')
        ->and($html)->toContain('href="https://example.com"');
});

test('raw HTML in the description is escaped, not run', function () {
    $product = Product::factory()->create(['description' => '<script>alert(1)</script>']);

    expect($product->descriptionHtml())->not->toContain('<script>')
        ->and($product->descriptionHtml())->toContain('&lt;script&gt;');
});

test('the excerpt is plain text without Markdown markers', function () {
    $product = Product::factory()->create(['description' => '**Bold** start & more']);

    expect($product->descriptionText())->toContain('Bold start & more')
        ->and($product->descriptionText())->not->toContain('**');
});

test('the configure page renders the description as formatted HTML, the listing an excerpt', function () {
    $currency = Currency::firstOrCreate(
        ['is_default' => true],
        ['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1.0, 'is_default' => true],
    );
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false,
        'retired' => false,
        'description' => "**Fast** plan\n\n- one\n- two",
    ]);
    Pricing::factory()->create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => 9.99, 'quarterly' => -1, 'semiannually' => -1,
        'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    // The listing shows a bounded plain excerpt: one long description must not
    // stretch every card in the grid.
    $this->get(route('client.store'))
        ->assertOk()
        ->assertDontSee('<strong>Fast</strong>', false)
        ->assertSee('Fast plan', false);

    $this->get(route('client.store.configure', $product))
        ->assertOk()
        ->assertSee('<strong>Fast</strong>', false)
        ->assertSee('<li>one</li>', false);
});

test('the product edit and create pages offer the Markdown toolbar', function () {
    $admin = Admin::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('data-md="bold"', false)
        ->assertSee('data-md-textarea', false);

    $this->actingAs($admin, 'admin')->get(route('admin.products.create'))
        ->assertOk()
        ->assertSee('data-md="bold"', false);
});

test('a tag that is not a script tag is text too, and an unsafe link loses its scheme', function () {
    // Measured on Str::markdown() without options: <script> had its opening
    // bracket escaped, but <img src=x onerror=...> went through untouched and
    // [x](javascript:...) became a live link - stored XSS on the public store.
    $product = Product::factory()->create([
        'description' => "<img src=x onerror=alert(1)>\n\n<iframe src=\"//evil.test\"></iframe>\n\n[click](javascript:alert(2))",
    ]);

    $html = $product->descriptionHtml();

    // The words survive as text - that is the point; what must not survive is
    // a tag the browser acts on, or a link it will follow.
    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('<iframe')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->toContain('&lt;img')
        ->and($html)->toContain('&lt;iframe');
});

test('a long description is cut in the listing rather than printed whole', function () {
    $currency = Currency::firstOrCreate(
        ['is_default' => true],
        ['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1.0, 'is_default' => true],
    );
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false, 'retired' => false,
        'description' => '**Bold** '.str_repeat('long copy ', 40),
    ]);
    Pricing::factory()->create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => 9.99, 'quarterly' => -1, 'semiannually' => -1,
        'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    $this->get(route('client.store'))
        ->assertOk()
        ->assertDontSee('<strong>Bold</strong>', false)
        ->assertSee('Bold long copy', false)
        ->assertDontSee(str_repeat('long copy ', 40), false);
});
