<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;

/**
 * The currency list had a Name column that printed the prefix: currencies have
 * no name, so the symbol appeared twice and the header promised something the
 * table does not hold.
 */
it('has one cell for every column header', function () {
    Currency::getDefault() ?? Currency::factory()->default()->create();
    Currency::factory()->create(['code' => 'ZZY', 'prefix' => '¤', 'is_default' => false]);

    $html = $this->actingAs(Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]), 'admin')
        ->get(route('admin.config.currencies'))->assertOk()->getContent();

    preg_match('#<table class="data-table">.*?</table>#s', $html, $table);
    preg_match('#<thead>(.*?)</thead>#s', $table[0], $head);
    preg_match('#<tr>\s*<td[^>]*>ZZY</td>(.*?)</tr>#s', $table[0], $row);

    expect($head[1])->not->toContain(__('common.table.name'))
        ->and(substr_count($head[1], '<th'))->toBe(substr_count('<td>'.$row[1], '<td'))
        ->and(substr_count($row[0], '¤'))->toBe(1);
});
