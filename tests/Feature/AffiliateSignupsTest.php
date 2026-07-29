<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;

/**
 * What an affiliate is shown about their own referrals.
 *
 * The dashboard has a Signups tile and it was set to zero in the controller,
 * so an affiliate who had brought in a dozen customers was told they had
 * brought in none. The commission list was matched on the word "affiliate"
 * appearing in a description rather than on the ledger rows that are actually
 * commission.
 */
function affiliateUser(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $affiliate = Affiliate::create([
        'client_id' => $client->id,
        'visitors' => 40,
        'pay_type' => 'percentage',
        'pay_amount' => 10,
        'onetime' => false,
        'balance' => 0,
        'withdrawn' => 0,
    ]);

    return [$user, $client, $affiliate];
}

test('an affiliate is shown how many customers they brought in', function () {
    [$user, , $affiliate] = affiliateUser();

    Client::factory()->count(3)->create(['affiliate_id' => $affiliate->id]);
    Client::factory()->count(2)->create();

    $this->actingAs($user)->get(route('client.affiliates.index'))
        ->assertOk()
        ->assertViewHas('stats', fn ($stats) => $stats['signups'] === 3);
});

test('an affiliate with no referrals is shown none', function () {
    [$user] = affiliateUser();

    $this->actingAs($user)->get(route('client.affiliates.index'))
        ->assertOk()
        ->assertViewHas('stats', fn ($stats) => $stats['signups'] === 0);
});

test('the commission list is the commission, not anything mentioning the word', function () {
    [$user, $client, $affiliate] = affiliateUser();

    Transaction::create([
        'client_id' => $client->id,
        'gateway' => 'affiliate_commission',
        'date' => now()->toDateString(),
        'description' => 'Affiliate referral commission',
        'amount_in' => 10,
        'amount_out' => 0,
        'fees' => 0,
        'rate' => 1,
    ]);

    // A payment that happens to mention the word.
    Transaction::create([
        'client_id' => $client->id,
        'gateway' => 'stripe',
        'date' => now()->toDateString(),
        'description' => 'Payment for the affiliate marketing plan',
        'amount_in' => 99,
        'amount_out' => 0,
        'fees' => 0,
        'rate' => 1,
    ]);

    $this->actingAs($user)->get(route('client.affiliates.index'))
        ->assertOk()
        ->assertViewHas('commissions', fn ($rows) => $rows->count() === 1
            && $rows->first()->gateway === 'affiliate_commission');
});
