<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The affiliate page and the two things it never let anybody do.
 *
 * There are routes for joining the programme and for withdrawing a balance,
 * and controller methods behind both. Neither appears in any view, so a
 * customer could do neither. The page still showed them a referral link -
 * "?ref=" with nothing after it for anybody who had not joined, which credits
 * nobody at all when shared.
 *
 * The withdrawal endpoint, reached directly, moved the balance and wrote no
 * record anywhere: no transaction, nothing in affiliate_withdrawals, and no
 * queue for the operator to work from. The money left the panel silently, and
 * the minimum payout setting was never consulted - AffiliateService has a
 * method that does all of that, and nothing called it.
 */
function affiliateVisitor(bool $joined = false, float $balance = 0): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $affiliate = null;

    if ($joined) {
        $affiliate = Affiliate::create([
            'client_id' => $client->id,
            'visitors' => 0,
            'pay_type' => 'percentage',
            'pay_amount' => 10,
            'onetime' => false,
            'balance' => $balance,
            'withdrawn' => 0,
        ]);
    }

    return [$user, $client, $affiliate];
}

test('someone who has not joined is asked to join, not given an empty link', function () {
    [$user] = affiliateVisitor();

    $html = $this->actingAs($user)->get(route('client.affiliates.index'))->assertOk()->getContent();

    expect($html)->toContain(route('client.affiliates.activate'))
        ->and($html)->not->toContain('?ref="');
});

test('joining creates the account', function () {
    Mail::fake();
    [$user, $client] = affiliateVisitor();

    $this->actingAs($user)->post(route('client.affiliates.activate'))->assertRedirect();

    expect(Affiliate::where('client_id', $client->id)->exists())->toBeTrue();
});

test('a member is given a link that names them', function () {
    [$user, , $affiliate] = affiliateVisitor(joined: true);

    $this->actingAs($user)->get(route('client.affiliates.index'))
        ->assertOk()
        ->assertSee('?ref='.$affiliate->id, false);
});

test('a member with a balance is offered the withdrawal form', function () {
    [$user] = affiliateVisitor(joined: true, balance: 100);

    $this->actingAs($user)->get(route('client.affiliates.index'))
        ->assertOk()
        ->assertSee(route('client.affiliates.withdraw'), false);
});

test('a withdrawal below the minimum is refused', function () {
    [$user, , $affiliate] = affiliateVisitor(joined: true, balance: 100);

    $this->actingAs($user)
        ->post(route('client.affiliates.withdraw'), ['amount' => 5])
        ->assertSessionHasErrors();

    expect((float) $affiliate->fresh()->balance)->toBe(100.0);
});

test('a withdrawal leaves a record behind', function () {
    [$user, $client, $affiliate] = affiliateVisitor(joined: true, balance: 100);

    $this->actingAs($user)
        ->post(route('client.affiliates.withdraw'), ['amount' => 40])
        ->assertRedirect();

    expect((float) $affiliate->fresh()->balance)->toBe(60.0)
        ->and((float) $affiliate->fresh()->withdrawn)->toBe(40.0)
        ->and(Transaction::where('client_id', $client->id)->where('gateway', 'affiliate_payout')->count())->toBe(1)
        ->and(DB::table('affiliate_withdrawals')->where('affiliate_id', $affiliate->id)->count())->toBe(1);
});

test('more than the balance is refused', function () {
    [$user, , $affiliate] = affiliateVisitor(joined: true, balance: 100);

    $this->actingAs($user)
        ->post(route('client.affiliates.withdraw'), ['amount' => 500])
        ->assertSessionHasErrors();

    expect((float) $affiliate->fresh()->balance)->toBe(100.0);
});
