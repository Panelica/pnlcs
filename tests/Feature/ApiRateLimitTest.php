<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use Database\Factories\ApiCredentialFactory;

/**
 * How many times the API lets someone guess.
 *
 * The admin login form allows ten attempts a minute. The API accepts the same
 * admin username and password - it is there for WHMCS-shaped clients - and had
 * no limit at all, so the form's limit could be walked around by posting the
 * guesses to any API endpoint instead, as fast as the server would answer.
 */
it('stops someone guessing an admin password at the api', function () {
    Admin::factory()->create(['username' => 'guessme', 'password' => bcrypt('the-real-one')]);

    $refused = 0;
    $tooMany = false;

    foreach (range(1, 15) as $attempt) {
        $response = $this->getJson('/api/v1/getstats?username=guessme&password=wrong-'.$attempt);

        if ($response->status() === 429) {
            $tooMany = true;
            break;
        }

        $refused++;
    }

    expect($tooMany)->toBeTrue()
        ->and($refused)->toBeLessThan(15);
});

// A credential doing its job is not the thing being guarded against, and it
// must not be locked out by somebody else's guessing from another address.
it('lets a working credential carry on', function () {
    $credential = ApiCredential::factory()->create();

    foreach (range(1, 12) as $ignored) {
        $this->withHeaders([
            'X-API-Key' => $credential->identifier,
            'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
        ])->getJson('/api/v1/getstats')->assertSuccessful();
    }
});
