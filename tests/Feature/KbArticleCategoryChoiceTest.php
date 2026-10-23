<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\KbArticle;
use App\Models\KbCategory;

/**
 * Both article forms offered "— Uncategorized —" (an empty category_id), but
 * an article must have a category: the rule is required and the column NOT
 * NULL. Choosing it always came back as a validation error.
 */
it('offers only categories an article can be saved under', function () {
    $category = KbCategory::create(['name' => 'Getting started']);
    KbArticle::create(['category_id' => $category->id, 'title' => 'First', 'article' => 'Body']);

    $html = $this->actingAs(Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]), 'admin')
        ->get(route('admin.config.knowledge-base'))->assertOk()->getContent();

    preg_match_all('#<select name="category_id"[^>]*>(.*?)</select>#s', $html, $selects);

    expect(count($selects[1]))->toBeGreaterThanOrEqual(2);
    foreach ($selects[0] as $i => $select) {
        expect($select)->toContain('required')
            ->and($selects[1][$i])->not->toContain('<option value="">')
            ->and($selects[1][$i])->toContain('<option value="'.$category->id.'"');
    }
});
