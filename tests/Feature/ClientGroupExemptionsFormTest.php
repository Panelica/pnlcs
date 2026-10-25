<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ClientGroup;

/*
 * A client group can exempt its clients from auto-suspend (suspend_exempt) and
 * auto-terminate (terminate_exempt). Both commands read the flags, the model
 * makes them fillable, and the "Never suspend this client" path through a
 * group is what an operator reaches for with a key account - but neither the
 * Add nor the Edit group form had a field, and the controller dropped them.
 */

function cgeAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a new group can be exempt from automatic suspension and termination', function () {
    $this->actingAs(cgeAdmin(), 'admin')->post(route('admin.config.client-groups.store'), [
        'name' => 'Key accounts', 'discount_percent' => 0, 'suspend_exempt' => '1', 'terminate_exempt' => '1',
    ])->assertSessionHasNoErrors();

    $group = ClientGroup::where('name', 'Key accounts')->sole();
    expect($group->suspend_exempt)->toBeTrue()->and($group->terminate_exempt)->toBeTrue();
});

test('editing a group sets and clears the exemptions', function () {
    $group = ClientGroup::create(['name' => 'Resellers']);

    $this->actingAs(cgeAdmin(), 'admin')->put(route('admin.config.client-groups.update', $group), [
        'name' => 'Resellers', 'suspend_exempt' => '1', 'terminate_exempt' => '0',
    ])->assertSessionHasNoErrors();
    expect($group->fresh()->suspend_exempt)->toBeTrue()->and($group->fresh()->terminate_exempt)->toBeFalse();

    $this->actingAs(cgeAdmin(), 'admin')->put(route('admin.config.client-groups.update', $group), [
        'name' => 'Resellers', 'suspend_exempt' => '0', 'terminate_exempt' => '1',
    ])->assertSessionHasNoErrors();
    expect($group->fresh()->suspend_exempt)->toBeFalse()->and($group->fresh()->terminate_exempt)->toBeTrue();
});

test('both forms offer the two switches, ticked where set', function () {
    ClientGroup::create(['name' => 'Key accounts', 'suspend_exempt' => true]);

    $html = $this->actingAs(cgeAdmin(), 'admin')->get(route('admin.config.client-groups'))->assertOk()->getContent();

    expect(substr_count($html, 'type="checkbox" name="suspend_exempt"'))->toBe(2)
        ->and(substr_count($html, 'type="checkbox" name="terminate_exempt"'))->toBe(2)
        ->and($html)->toContain('name="suspend_exempt" value="1" checked')
        ->toContain(e(__('admin.client_groups.suspend_exempt')));
});
