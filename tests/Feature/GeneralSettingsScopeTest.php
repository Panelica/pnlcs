<?php

use App\Models\Admin;
use App\Models\Setting;

/**
 * One endpoint, two screens, eleven switches turned off by the wrong one.
 *
 * admin.settings.general.update is posted to by two different forms: the
 * general settings screen, which owns eleven checkboxes, and the AI section of
 * the languages screen, which carries an OpenAI key and a model name and
 * nothing else.
 *
 * The handler defaulted every one of those eleven to '0' whenever it did not
 * see it, because an unticked checkbox is absent from a request rather than
 * false. That reasoning is correct for the screen the checkboxes live on and
 * wrong for every other screen: saving an OpenAI key silently switched off
 * outgoing mail, fraud screening, Google login, email verification, the
 * knowledge base, two-factor verification, proforma invoicing and automatic
 * termination, and said nothing about it.
 *
 * A form now carries its own switches: a hidden '0' in front of each checkbox
 * means an unticked one posts a value instead of vanishing, so a form that does
 * not contain a switch no longer has an opinion about it.
 */
function switchScopeAdmin(): Admin
{
    return Admin::factory()->create();
}

/** The switches the general settings screen owns. */
const SCOPED_SWITCHES = [
    'MailEnabled',
    'InvoiceNumberYearlyReset',
    'AutoTerminationEnabled',
    'MaxMindEnabled',
    'FraudLabsEnabled',
    'GoogleLoginEnabled',
    'EmailVerificationRequired',
    'KnowledgeBaseEnabled',
    'TwilioVerifyEnabled',
    'ProformaEnabled',
    'HidePaidProformas',
];

test('saving the openai key on the languages screen leaves every switch alone', function () {
    foreach (SCOPED_SWITCHES as $switch) {
        Setting::set($switch, '1', 'general');
    }

    // Exactly what the AI section of the languages screen posts: a key, a
    // model, and nothing else.
    $this->actingAs(switchScopeAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'OpenAIApiKey' => 'sk-test-key',
            'OpenAIModel' => 'gpt-4o',
        ])->assertRedirect();

    foreach (SCOPED_SWITCHES as $switch) {
        expect(Setting::get($switch))->toBe('1', "{$switch} was switched off by a screen that does not own it");
    }

    expect(Setting::get('OpenAIModel'))->toBe('gpt-4o');
});

test('the general screen can still switch something off', function () {
    Setting::set('MailEnabled', '1', 'general');
    Setting::set('KnowledgeBaseEnabled', '1', 'general');

    // The general screen carries a hidden '0' in front of each checkbox, so an
    // unticked one posts '0' rather than disappearing.
    $post = ['Email' => 'ops@example.com'];
    foreach (SCOPED_SWITCHES as $switch) {
        $post[$switch] = '0';
    }
    $post['MailEnabled'] = '1';

    $this->actingAs(switchScopeAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), $post)
        ->assertRedirect();

    expect(Setting::get('MailEnabled'))->toBe('1')
        ->and(Setting::get('KnowledgeBaseEnabled'))->toBe('0');
});

test('an explicit off is honoured, which is what the hidden field sends', function () {
    // A guard rather than a reproduction: posting '0' explicitly always worked.
    // What did not work was the browser posting nothing, which is what an
    // unticked checkbox does - see the hidden-field test below. MaintenanceMode
    // is read by app/Http/Middleware/MaintenanceMode.php:46, so a switch that
    // could only ever be turned on left the client portal shut.
    Setting::set('MaintenanceMode', '1', 'general');

    $this->actingAs(switchScopeAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'Email' => 'ops@example.com',
            'MaintenanceMode' => '0',
        ])->assertRedirect();

    expect(Setting::get('MaintenanceMode'))->toBe('0');
});

test('the general screen posts a hidden zero for every switch it owns', function () {
    $html = $this->actingAs(switchScopeAdmin(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    // MaintenanceMode is included here and nowhere else: it was never in the
    // handler's defaulting list, so unticking it posted nothing and the stored
    // '1' survived every save - the portal could be shut but not reopened.
    // A switch with no hidden partner says nothing when it is left unticked,
    // which is how it becomes impossible to turn off.
    $missing = [];
    foreach ([...SCOPED_SWITCHES, 'MaintenanceMode'] as $switch) {
        if (! str_contains($html, '<input type="hidden" name="'.$switch.'" value="0">')) {
            $missing[] = $switch;
        }
    }

    expect($missing)->toBe([]);
});
