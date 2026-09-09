<?php

namespace App\Services;

use App\Events\ClientCreated;
use App\Http\Middleware\AffiliateTracking;
use App\Models\Client;
use App\Models\Setting;
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
            // An account opened through Google has no password of its own.
            // Inventing one would leave a hash its owner never chose and a
            // reset link that quietly turns a Google account into a local one.
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'google_id' => $validated['google_id'] ?? null,
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
            'address2' => $validated['address2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            // Falls back to the operator's own country, not to a hardcoded
            // one: the tax rate is looked up by country, so a wrong default
            // is a wrong invoice. The admin "create client" screen has always
            // used this setting; the two doors now agree.
            'country' => $validated['country'] ?? Setting::get('Country', 'US'),
            'tax_id' => $validated['tax_id'] ?? null,
            // The billing identity, where the seller's rules ask for it.
            'client_type' => $validated['client_type'] ?? null,
            'tax_office' => $validated['tax_office'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
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
