<?php

use App\Http\Middleware\AffiliateTracking;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\User;

/*
 * An affiliate's own word for their link: ?ref=ayse rather than ?ref=12.
 *
 * Links carried the row id, which says nothing to the people it is shared
 * with and tells anyone how many affiliates there are.
 */

function apcAffiliate(?string $code = null): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $affiliate = Affiliate::create(['client_id' => $client->id, 'code' => $code, 'visitors' => 0, 'pay_type' => 'percentage', 'pay_amount' => 10, 'balance' => 0, 'withdrawn' => 0]);

    return [$user, $affiliate];
}

it('tracks a visit that came with the personal code', function () {
    [, $affiliate] = apcAffiliate('ayse');

    $response = test()->get('/?ref=Ayse');

    expect((string) $response->getCookie(AffiliateTracking::COOKIE)?->getValue())->toBe((string) $affiliate->id)
        ->and($affiliate->fresh()->visitors)->toBe(1);
});

it('keeps the numeric link working', function () {
    [, $affiliate] = apcAffiliate('ayse');

    expect((string) test()->get('/?ref='.$affiliate->id)->getCookie(AffiliateTracking::COOKIE)?->getValue())->toBe((string) $affiliate->id);
});

it('lets the affiliate set a code and shows the link with it', function () {
    [$user, $affiliate] = apcAffiliate();

    test()->actingAs($user)->post(route('client.affiliates.code'), ['code' => 'Ayse-Web'])->assertSessionHasNoErrors();

    expect($affiliate->fresh()->code)->toBe('ayse-web');
    test()->actingAs($user)->get(route('client.affiliates.index'))->assertOk()->assertSee('?ref=ayse-web', false);
});

it('refuses a code that is taken, only digits, too short or has other characters', function () {
    apcAffiliate('ayse');
    [$user, $affiliate] = apcAffiliate();

    foreach (['ayse', '12345', 'ab', 'ayşe', 'a b', '-abc'] as $bad) {
        test()->actingAs($user)->post(route('client.affiliates.code'), ['code' => $bad])->assertSessionHasErrors('code');
    }

    expect($affiliate->fresh()->code)->toBeNull();
});

it('lets the affiliate clear the code', function () {
    [$user, $affiliate] = apcAffiliate('ayse');

    test()->actingAs($user)->post(route('client.affiliates.code'), ['code' => ''])->assertSessionHasNoErrors();

    expect($affiliate->fresh()->code)->toBeNull()->and($affiliate->fresh()->link())->toEndWith('?ref='.$affiliate->id);
});

it('lets an admin set the code', function () {
    [, $affiliate] = apcAffiliate();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'A', 'permissions' => ['manage_affiliates']])->id]);

    test()->actingAs($admin, 'admin')->put(route('admin.affiliates.update', $affiliate), ['pay_type' => 'percentage', 'pay_amount' => 10, 'code' => 'partner-x'])
        ->assertSessionHasNoErrors();

    expect($affiliate->fresh()->code)->toBe('partner-x');
    test()->actingAs($admin, 'admin')->get(route('admin.affiliates.show', $affiliate))->assertSee('?ref=partner-x', false);
});
