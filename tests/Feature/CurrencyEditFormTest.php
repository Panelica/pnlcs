<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;

/**
 * The edit dialog had a "Set as default" box that updateCurrency() never read:
 * ticking it and saving left the default where it was. The default is changed
 * with the "Make default" button, which the list already offers.
 */
it('offers no default switch that saving would ignore', function () {
    Currency::getDefault() ?? Currency::factory()->default()->create();
    Currency::factory()->create(['code' => 'ZZX', 'is_default' => false]);

    $html = $this->actingAs(Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]), 'admin')
        ->get(route('admin.config.currencies'))->assertOk()->getContent();

    preg_match('#id="modal-edit-currency".*?</form>#s', $html, $dialog);

    expect($dialog[0] ?? '')->not->toBe('')
        ->and($dialog[0])->not->toContain('name="default"')
        ->and($html)->toContain(__('admin.currencies.make_default'));
});
