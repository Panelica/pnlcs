<?php

use App\Models\ApiCredential;
use Database\Factories\ApiCredentialFactory;

/**
 * The endpoint that decrypted whatever it was given.
 *
 * decryptpassword ran the application key over any ciphertext a caller sent,
 * and handed back the plain text. Nothing in the application has ever called
 * it, and it cannot read what this application stores today - the secrets in
 * the database are written with encryptString, which it does not understand -
 * but it is a standing offer to decrypt anything that arrives in the form it
 * does understand, made to anybody holding an API credential.
 */
function oracleHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

it('does not decrypt what it is handed', function () {
    $ciphertext = encrypt('the-real-access-key');

    $response = $this->withHeaders(oracleHeaders())
        ->postJson('/api/v1/decryptpassword', ['password2' => $ciphertext]);

    expect($response->json('password'))->not->toBe('the-real-access-key');
});

it('says the endpoints that do nothing do nothing', function () {
    foreach (['resetpassword', 'activatemodule', 'deactivatemodule'] as $endpoint) {
        $this->withHeaders(oracleHeaders())
            ->postJson('/api/v1/'.$endpoint, [])
            ->assertStatus(501);
    }
});
