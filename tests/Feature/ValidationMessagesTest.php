<?php

use Illuminate\Support\Facades\Validator;

/*
 * Every validation rule answers with a sentence, never its translation key.
 *
 * The translation loader was given only the application's lang directory, so
 * the framework's messages were never read and any rule the application did
 * not repeat - "in", "date", "integer", "exists" and 93 more - reached the
 * form, and the API, as "validation.in". Seen live on the API reference's own
 * example for getmoduleconfigurationparameters.
 */

test('no validation rule shows its raw key, in any shipped language', function () {
    $rules = array_keys(array_diff_key(
        require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'),
        ['custom' => 1, 'attributes' => 1]
    ));

    $raw = [];
    foreach (['en', 'tr', 'pl', 'zh', 'de'] as $locale) {
        app()->setLocale($locale);
        foreach ($rules as $rule) {
            $line = trans('validation.'.$rule);
            if ($line === 'validation.'.$rule) {
                $raw[] = "{$locale}: {$rule}";
            }
        }
    }

    expect($raw)->toBe([]);
});

test('a value outside the allowed list is refused with a sentence', function () {
    app()->setLocale('en');
    $message = Validator::make(['type' => 'x'], ['type' => 'in:a,b'])->errors()->first('type');

    expect($message)->toBe('The selected type is invalid.');
});

test('the API answers a failed rule with that sentence', function () {
    $credential = \App\Models\ApiCredential::factory()->create();

    $this->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => \Database\Factories\ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->getJson('/api/v1/getmoduleconfigurationparameters?moduleType=example&moduleName=example')
        ->assertStatus(422)
        ->assertJsonPath('result', 'error')
        ->assertJsonPath('message', 'The selected module type is invalid.');
});
