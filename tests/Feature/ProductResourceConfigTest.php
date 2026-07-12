<?php

use App\Models\Admin;
use App\Models\Product;

/**
 * Faz 4b: the product edit page exposes the Panelica managed resource limits,
 * and saving merges them into config_options without dropping feature text.
 */

it('renders the panelica resources section on the product edit page', function () {
    $admin   = Admin::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('Panelica Resources')
        ->assertSee('Managed mode')
        ->assertSee('Inode Quota (-1 = unlimited)');
});

it('saves managed resource limits into config_options and preserves feature text', function () {
    $admin   = Admin::factory()->create();
    $product = Product::factory()->create([
        'config_options' => json_encode(['f1' => '2 GB Storage', 'panelica_plan_id' => 'old-plan']),
    ]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.products.update', $product), [
            'name' => $product->name, 'group_id' => $product->group_id,
            'type' => 'hosting', 'pay_type' => 'recurring',
            'res_section' => 1, 'res_managed' => 1,
            'res_cpu_percent' => 200, 'res_memory_mb' => 2048,
            'res_inode_quota' => 250000, 'res_iops' => 1000, 'res_ssh_level' => 'jailed',
        ])
        ->assertRedirect();

    $cfg = json_decode($product->fresh()->config_options, true);
    expect($cfg['res_managed'])->toBe(1)
        ->and($cfg['res_cpu_percent'])->toBe(200)
        ->and($cfg['res_memory_mb'])->toBe(2048)
        ->and($cfg['res_inode_quota'])->toBe(250000)
        ->and($cfg['res_iops'])->toBe(1000)
        ->and($cfg['res_ssh_level'])->toBe('jailed')
        ->and($cfg['f1'])->toBe('2 GB Storage');  // feature text preserved
});
