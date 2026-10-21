<?php

use App\Models\Admin;
use App\Models\Language;

/*
 * The 27 admin reports wrote their titles, descriptions, categories and
 * column names in English, whatever the admin's language. The report classes
 * keep their English words (a report's URL is made from its title); the words
 * are translated where they are shown.
 */

beforeEach(function () {
    Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::updateOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'native_name' => 'Türkçe', 'is_active' => true, 'is_default' => false]);
});

test('the reports page reads in the admin\'s language, with the same addresses', function () {
    $admin = Admin::factory()->create(['language' => 'tr']);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.reports.index').'?lang=tr')->assertOk()->getContent();

    expect($html)->toContain('Gelir Özeti')->toContain('Eğilimleriyle aylık gelir dağılımı')->toContain('Finans')
        ->not->toContain('>Income Summary<')
        ->toContain(route('admin.reports.show', 'income-summary'));
});

test('a report shows its title and columns in the admin\'s language', function () {
    $admin = Admin::factory()->create(['language' => 'tr']);

    $this->actingAs($admin, 'admin')->get(route('admin.reports.show', 'customer-retention').'?lang=tr')->assertOk()
        ->assertSee('Müşteri Bağlılığı')->assertSee('Ölçüt')->assertSee('Bağlılık oranı')->assertDontSee('Retention Rate');
});

test('a word with no translation is shown as the report wrote it', function () {
    app()->setLocale('tr');

    expect(report_text('Income'))->toBe('Gelir')
        ->and(report_text('Usage %'))->toBe('Kullanım %')
        ->and(report_text('Some Third Party Column'))->toBe('Some Third Party Column')
        ->and(report_text('#'))->toBe('#');
});

test('the CSV export carries the translated column names', function () {
    $admin = Admin::factory()->create(['language' => 'tr']);

    $csv = $this->actingAs($admin, 'admin')->get(route('admin.reports.export', 'customer-retention').'?lang=tr')->streamedContent();

    expect(strtok($csv, "\n"))->toContain('Ölçüt');
});
