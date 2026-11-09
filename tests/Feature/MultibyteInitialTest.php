<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;

/**
 * Avatar initials for names in any script (#420).
 *
 * The client top bar, the staff list and the container cards took the first
 * byte of a name with substr() and upper-cased it with strtoupper(), so a
 * Chinese, Japanese or Turkish name rendered a broken byte or an empty circle.
 */
it('takes the first character, not the first byte', function () {
    expect(initial('张伟'))->toBe('张')
        ->and(initial('山田'))->toBe('山')
        ->and(initial('şule'))->toBe('Ş')
        ->and(initial('élise'))->toBe('É')
        ->and(initial('john'))->toBe('J');
});

it('falls back when the name is empty', function () {
    expect(initial(null, 'U'))->toBe('U')
        ->and(initial('   ', 'U'))->toBe('U')
        ->and(initial(''))->toBe('');
});

it('keeps an emoji whole when intl is available', function () {
    $family = "\u{1F469}\u{200D}\u{1F469}\u{200D}\u{1F467}";   // one emoji made of several code points
    $first = initial($family.' Ailesi');

    expect(mb_check_encoding($first, 'UTF-8'))->toBeTrue();
    if (function_exists('grapheme_substr')) {
        expect($first)->toBe($family);
    }
});

it('shows a Chinese name\'s initial in the client top bar', function () {
    $user = User::factory()->create(['first_name' => '张伟']);
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertOk()
        ->assertSee('<div class="pn-avatar">张</div>', false);
});

it('shows Turkish and Japanese initials in the staff list', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    Admin::factory()->create(['first_name' => 'Şule', 'last_name' => '山田']);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.admins'))
        ->assertOk()
        ->assertSee('Ş山', false);
});
