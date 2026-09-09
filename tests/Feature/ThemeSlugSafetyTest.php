<?php

use App\Services\ThemeManager;

/*
 * A theme slug is a directory name under themes/. Nothing checked what it
 * contained, and delete() only asked whether the directory existed - so a
 * slug of ".." named the application root itself.
 */
test('a theme slug that walks out of the themes directory is refused', function () {
    $manager = app(ThemeManager::class);

    foreach (['..', '../storage', '.', 'a/b', "x\0"] as $slug) {
        expect($manager->delete($slug)['success'])->toBeFalse("slug {$slug}")
            ->and($manager->activate($slug))->toBeFalse("slug {$slug}");
    }

    expect(is_dir(base_path('app')))->toBeTrue();
});
