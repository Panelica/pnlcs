<?php

namespace App\Services;

use App\Events\ClientCreated;
use App\Http\Middleware\AffiliateTracking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Opening an account, wherever it happens.
 *
 * This used to live inside the register action only. Then checkout learned to
 * open an account in-line - a visitor who has just configured a product should
 * not be sent away to a register page and made to start over - and the two
 * paths must create exactly the same thing: user, client, ownership link,
 * affiliate attribution, ClientCreated event. One copy, or they drift.
 */
class ClientRegistrationService
{
    /** @return array{0: User, 1: Client} */
    public function register(array $validated, Request $request): array
    {
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $client = Client::create([
            // Explicit, not left to the column default: the created model in
            // memory does not carry a default the database applied, and
            // checkout reads the status off this very instance one line later.
            'status' => \App\Enums\ClientStatus::Active,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? 'US',
            'phone_number' => $validated['phone_number'] ?? null,
        ]);
        $client->users()->attach($user->id, ['owner' => true]);

        // The referral cookie dropped by AffiliateTracking becomes a real link
        // here, whichever door the account came through.
        $referralId = $request->cookie(AffiliateTracking::COOKIE);
        if ($referralId) {
            app(AffiliateService::class)->linkClientToAffiliate($client, (int) $referralId);
        }

        event(new ClientCreated($client));

        return [$user, $client];
    }
}
