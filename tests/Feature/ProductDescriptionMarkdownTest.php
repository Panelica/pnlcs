<?php

use App\Models\Admin;
use App\Models\Product;

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
        ->and($product->descriptionHtml())->toContain('&lt;script>');
});

test('the excerpt is plain text without Markdown markers', function () {
    $product = Product::factory()->create(['description' => '**Bold** start & more']);

    expect($product->descriptionText())->toContain('Bold start & more')
        ->and($product->descriptionText())->not->toContain('**');
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
