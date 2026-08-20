<?php

use App\Models\Admin;
use App\Models\AdminRole;

/*
 * The add-server modal, seen by someone holding a Panelica API key pair.
 *
 * The Panelica panel hands the operator exactly two strings - an API Key
 * (pk_live_...) shown once, and an API Secret (sk_live_...) shown once - and
 * this modal put seven generic fields in front of them: username, password/API
 * token, access hash, port. Nothing said which of the two strings went where.
 * The fields now retitle themselves to the selected panel's own words, hide
 * what that panel does not use, and a hint says where the values come from.
 */

test('the modal knows what each panel type calls its credentials', function () {
    $html = test()->actingAs(
        Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]),
        'admin'
    )->get(route('admin.config.servers'))->assertOk()->getContent();

    // The tuning map and the handles it needs. If a field loses its data-role
    // the tuning silently stops applying to it, so the markup is the contract.
    expect($html)->toContain('SERVER_TYPE_TUNING')
        ->and($html)->toContain("passwordPlaceholder: 'pk_live_...'")
        ->and($html)->toContain("hashPlaceholder: 'sk_live_...'")
        ->and($html)->toContain('data-role="username-group"')
        ->and($html)->toContain('data-role="password-label"')
        ->and($html)->toContain('data-role="hash-label"')
        ->and($html)->toContain('data-role="type-hint"')
        // Both forms take part: the add form and the edit form.
        ->and($html)->toContain('data-role="edit-username-group"')
        ->and($html)->toContain("serverTypeTuning(typeSelect, 'edit')");
});

test('the credential labels match what the module actually reads', function () {
    // modules/Servers/Panelica reads password as the API Key and access_hash as
    // the API Secret. If that mapping ever changes, the modal's words lie.
    $module = file_get_contents(base_path('modules/Servers/Panelica/PanelicaModule.php'));

    expect($module)->toContain('$server->password ?? \'\';   // pk_live_...')
        ->and($module)->toContain('$server->access_hash ?? \'\'; // sk_live_...');
});
