<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\ClientInviteService;
use App\Support\ClientPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The account owner's Users page: who else can sign in to this account, with
 * what permissions, and the invitations still open.
 *
 * Invitations, their acceptance and the per-login permissions already
 * existed, but only the API could send one; an owner who wanted their
 * accountant to see the invoices had to ask the host to do it for them, and
 * nobody could see or take away a login's access.
 *
 * Only the owner manages users. The permissions are the ones
 * ClientPermissions enforces on every client-area route.
 */
class AccountUserController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $client = $this->ownedClient();

        return view('client.account.users', [
            'client' => $client,
            'logins' => $client->users()->orderByDesc('user_client.owner')->orderBy('users.email')->get(),
            'invites' => UserInvite::where('client_id', $client->id)->whereNull('accepted_at')
                ->orderByDesc('id')->get()->filter->isOpen()->values(),
            'permissionNames' => ClientPermissions::ALL,
        ]);
    }

    public function invite(Request $request, ClientInviteService $invites)
    {
        $client = $this->ownedClient();
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(ClientPermissions::ALL),
        ]);
        $email = strtolower($validated['email']);

        if ($client->users()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('client.account_users.already_on_account')])->withInput();
        }

        $invites->send($client, $email, array_values(array_unique($validated['permissions'])));
        ActivityLog::log('Invitation sent to '.$email.' by the account owner', auth()->user()->email, $client->id);

        return back()->with('success', __('client.account_users.invited', ['email' => $email]));
    }

    public function update(Request $request, User $user)
    {
        $client = $this->ownedClient();
        $this->guardMember($client, $user);

        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(ClientPermissions::ALL),
        ]);

        $client->users()->updateExistingPivot($user->id, [
            'permissions' => json_encode(array_values(array_intersect(ClientPermissions::ALL, $validated['permissions']))),
        ]);
        ActivityLog::log('Permissions of '.$user->email.' changed by the account owner', auth()->user()->email, $client->id);

        return back()->with('success', __('client.account_users.updated', ['email' => $user->email]));
    }

    public function destroy(User $user)
    {
        $client = $this->ownedClient();
        $this->guardMember($client, $user);

        $client->users()->detach($user->id);
        ActivityLog::log('Access of '.$user->email.' removed by the account owner', auth()->user()->email, $client->id);

        return back()->with('success', __('client.account_users.removed', ['email' => $user->email]));
    }

    public function cancelInvite(UserInvite $invite)
    {
        $client = $this->ownedClient();
        abort_unless($invite->client_id === $client->id && $invite->accepted_at === null, 404);

        $invite->delete();

        return back()->with('success', __('client.account_users.invite_cancelled', ['email' => $invite->email]));
    }

    /** The account being looked at, when the signed-in login owns it. */
    private function ownedClient(): Client
    {
        $client = $this->currentClient();
        abort_unless($client && $client->users()->whereKey(auth()->id())->wherePivot('owner', true)->exists(), 403);

        return $client;
    }

    /** Another, non-owner login on this account: the owner cannot lock themself out. */
    private function guardMember(Client $client, User $user): void
    {
        $pivot = $client->users()->whereKey($user->id)->first()?->pivot;
        abort_unless($pivot && ! $pivot->owner && $user->id !== auth()->id(), 404);
    }
}
