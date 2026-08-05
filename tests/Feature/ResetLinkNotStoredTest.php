<?php

use App\Mail\BulkMassMail;
use App\Models\Client;
use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * What is left behind after a password reset is requested.
 *
 * The token is hashed in password_reset_tokens, and the mailable says in its
 * own comment that the token is never written to the log. But every sent mail
 * is copied into the emails table, body and all - so the link, with the working
 * token in it, was kept in plain text for as long as the row lived, shown in
 * the customer's email history, and handed out by the getemails endpoint to
 * any credential with read access. Hashing the token and then keeping the link
 * beside it protects nothing.
 */
it('does not keep the reset link in the mail history', function () {
    $client = Client::factory()->create(['email' => 'resetme@test.local']);
    $user = User::factory()->create(['email' => 'resetme@test.local']);
    $user->clients()->attach($client->id);

    $this->post(route('client.password.email'), ['email' => 'resetme@test.local'])
        ->assertRedirect();

    $stored = Email::where('to', 'resetme@test.local')->latest('id')->first();

    expect($stored)->not->toBeNull('the mail should still be recorded');

    // Whatever is kept must not carry the token that opens the account.
    $token = DB::table('password_reset_tokens')->where('email', 'resetme@test.local')->value('token');

    expect($stored->message)->not->toContain('reset-password/')
        ->and($stored->message)->not->toContain((string) $token);

    expect(DB::table('password_reset_tokens')->where('email', 'resetme@test.local')->exists())->toBeTrue();
});

it('still keeps ordinary mail in full', function () {
    $client = Client::factory()->create(['email' => 'ordinary@test.local']);

    Mail::to('ordinary@test.local')
        ->send(new BulkMassMail('A subject', 'The whole message body', 'Ordinary'));

    $stored = Email::where('to', 'ordinary@test.local')->latest('id')->first();

    expect($stored?->message)->toContain('The whole message body');
});
