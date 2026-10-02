<?php

namespace App\Services;

use App\Mail\BulkMassMail;
use App\Models\Client;
use App\Models\UserInvite;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Invites a login to a customer account: stores the invitation (a hash of
 * the link's token, never the token) and mails the link to the address.
 *
 * Used by the API's CreateClientInvite and by the account owner's Users page.
 */
class ClientInviteService
{
    /**
     * @param  list<string>  $permissions  ClientPermissions names
     * @param  int  $invitedBy  the admin's id, or 0 when the account owner sent it
     */
    public function send(Client $client, string $email, array $permissions, int $invitedBy = 0): UserInvite
    {
        $email = strtolower(trim($email));

        // A newer invitation replaces an open one for the same address, so
        // only one working link is ever out there.
        UserInvite::where('client_id', $client->id)->where('email', $email)->whereNull('accepted_at')->delete();

        $plain = Str::random(64);
        $invite = UserInvite::create([
            'token' => hash('sha256', $plain),
            'email' => $email,
            'client_id' => $client->id,
            'invited_by' => $invitedBy,
            'permissions' => $permissions,
        ]);

        $link = route('client.invite.show', $plain);
        $locale = $client->language ?: config('app.locale');
        $account = trim($client->company_name ?: $client->first_name.' '.$client->last_name);
        Mail::to($email)->queue(new BulkMassMail(
            __('client.invite.mail_subject', ['company' => company_name()], $locale),
            __('client.invite.mail_body', ['account' => $account, 'company' => company_name(), 'link' => $link, 'days' => UserInvite::VALID_DAYS], $locale),
            $email
        ));

        return $invite;
    }
}
