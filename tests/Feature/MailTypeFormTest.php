<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/**
 * With no mail type stored, mail goes out through the mailer in .env
 * (App\Support\MailTransport). The settings form showed PHP Mail selected for
 * that state, so the first save of General Settings stored php_mail and moved
 * a working .env SMTP setup onto sendmail.
 */
function mailTypeAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function mailTypeOptions(string $html): string
{
    preg_match('#<select name="MailType"[^>]*>(.*?)</select>#s', $html, $select);

    return $select[1] ?? '';
}

test('with no mail type stored the form selects the server default', function () {
    Setting::where('setting', 'MailType')->delete();

    $options = mailTypeOptions($this->actingAs(mailTypeAdmin(), 'admin')
        ->get(route('admin.settings.general'))->assertOk()->getContent());

    expect($options)->toMatch('#<option value=""\s+selected#')
        ->and($options)->not->toMatch('#<option value="php_mail"\s+selected#');
});

test('saving the form as it came leaves the mailer in .env in charge', function () {
    Setting::where('setting', 'MailType')->delete();

    $this->actingAs(mailTypeAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), ['MailType' => '', 'CompanyName' => 'Shop'])
        ->assertRedirect();

    expect(Setting::where('setting', 'MailType')->exists())->toBeFalse();
});

test('a stored choice is still shown and can be given back to .env', function () {
    Setting::set('MailType', 'smtp', 'general');
    $admin = mailTypeAdmin();

    $options = mailTypeOptions($this->actingAs($admin, 'admin')
        ->get(route('admin.settings.general'))->getContent());
    expect($options)->toMatch('#<option value="smtp"\s+selected#');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.general.update'), ['MailType' => ''])
        ->assertRedirect();

    expect(Setting::get('MailType'))->toBeNull();
});
