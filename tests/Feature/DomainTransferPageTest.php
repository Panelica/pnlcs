<?php

use App\Models\Client;
use App\Models\User;

/*
 * The transfer page, as a customer sees it.
 *
 * It was a lone card pushed to the left with two bare fields - no symmetry
 * with the rest of the client area and nothing that said what would happen
 * after submitting, on the one page where the visitor is mid-move between two
 * companies and nervous. It now shares the cart's two-column shape: the form
 * on the left, the three steps and the timeline on the right, centred.
 */

test('the page carries the form and the orientation side by side', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $html = $this->actingAs($user)
        ->get(route('client.domains.transfer', ['domain' => 'example.com']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('transfer-layout')          // the shared two-column grid
        ->and($html)->toContain('transfer-steps')        // the three-step walkthrough
        ->and($html)->toContain(__('client.domains.transfer_how_title'))
        ->and($html)->toContain(__('client.domains.transfer_duration'))
        ->and($html)->toContain('value="example.com"')   // the searched domain carries in
        ->and($html)->toContain('name="epp_code"');
});

test('the walkthrough exists in every shipped language', function () {
    // These keys were added in all thirty languages at once; a language where
    // the panel falls back to English on this page failed its promise.
    foreach (array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR)) as $locale) {
        $client = (array) require base_path("lang/$locale/client.php");
        $flat = json_encode($client);
        foreach (['transfer_how_title', 'transfer_step_unlock', 'transfer_step_epp', 'transfer_step_paste', 'transfer_duration'] as $key) {
            expect($flat)->toContain($key);
        }
    }
});
