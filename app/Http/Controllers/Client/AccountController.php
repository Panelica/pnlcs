<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Mail\LoginEmailChangedMail;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    use ResolvesClient;

    /** ISO 3166-1 alpha-2 country list for the profile form. */
    private const COUNTRIES = [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina', 'AM' => 'Armenia',
        'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BH' => 'Bahrain', 'BD' => 'Bangladesh',
        'BY' => 'Belarus', 'BE' => 'Belgium', 'BA' => 'Bosnia and Herzegovina', 'BR' => 'Brazil',
        'BG' => 'Bulgaria', 'KH' => 'Cambodia', 'CA' => 'Canada', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'CR' => 'Costa Rica', 'HR' => 'Croatia', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
        'DK' => 'Denmark', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'EE' => 'Estonia',
        'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France', 'GE' => 'Georgia', 'DE' => 'Germany',
        'GH' => 'Ghana', 'GR' => 'Greece', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
        'IN' => 'India', 'ID' => 'Indonesia', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
        'IT' => 'Italy', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
        'KW' => 'Kuwait', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
        'MY' => 'Malaysia', 'MT' => 'Malta', 'MX' => 'Mexico', 'MD' => 'Moldova', 'MA' => 'Morocco',
        'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'MK' => 'North Macedonia',
        'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PS' => 'Palestine', 'PA' => 'Panama',
        'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
        'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'RS' => 'Serbia',
        'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'ZA' => 'South Africa',
        'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'TW' => 'Taiwan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TN' => 'Tunisia', 'TR' => 'Türkiye',
        'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
        'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
    ];

    public function profile()
    {
        $user = auth()->user();
        $client = $this->currentClient();
        // The blade renders a country <select> from $countries; without it the
        // dropdown had nothing but its placeholder and no country could be set.
        $countries = \App\Support\Countries::all();

        // Most logins have one account and never see the switch.
        $accounts = $user->clients()->orderBy('id')->get();

        // Custom fields the client is allowed to see and edit (admin-only ones
        // stay out of the client area entirely), with the values already set.
        $customFields = collect();
        if ($client) {
            $customFields = \App\Models\CustomField::clientFields()
                ->where('admin_only', false)
                ->with(['values' => fn ($q) => $q->where('rel_id', $client->id)])
                ->get();
        }

        return view('client.account.profile', compact('user', 'client', 'countries', 'accounts', 'customFields'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $client = $this->currentClient();

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            // The column will not hold null, so asking is better than crashing.
            'country' => 'required|string|size:2',
            'phone_number' => 'nullable|string|max:50',
            'new_password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'new_password_confirmation' => 'required_with:new_password|string',
        ]);

        $previousEmail = (string) $user->email;
        $changingLogin = strcasecmp($previousEmail, (string) $request->email) !== 0;
        $changingPassword = ! empty($request->new_password);

        // The sign-in address is where a password reset is delivered, so
        // changing it is as good as changing the password - and that asks for
        // the current one. Setting a new password also asks for it.
        if ($changingLogin || $changingPassword) {
            $request->validate(['current_password' => 'required|string']);

            if (! Hash::check((string) $request->current_password, (string) $user->password)) {
                return back()->withInput()->withErrors([
                    'current_password' => __('messages.error.current_password_incorrect'),
                ]);
            }
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
        ]);

        if ($changingPassword) {
            $user->update(['password' => Hash::make($request->new_password)]);

            // A "remember me" cookie signs its holder in on its own for as long
            // as the token behind it stays put. Changing the password is how
            // somebody ends a session they did not start, so the token goes
            // with it and the cookie stops working - this one included, which
            // is why the current session is re-remembered below.
            $user->setRememberToken(Str::random(60));
            $user->save();

            Auth::guard('web')->login($user, true);
        }

        if ($changingLogin) {
            // The address losing the account hears about it; that is the one
            // warning somebody has if it was not them.
            try {
                Mail::to($previousEmail)->send(
                    new LoginEmailChangedMail($previousEmail, (string) $request->email)
                );
            } catch (\Throwable $e) {
                Log::warning('Could not tell the previous address its account moved', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($client) {
            $client->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'company_name' => $request->company_name,
                'tax_id' => $request->tax_id,
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'state' => $request->state,
                'postcode' => $request->postcode,
                'country' => $request->country,
                'phone_number' => $request->phone_number,
            ]);
        }

        // Custom field values editable from the client area (admin-only fields
        // are never rendered, so only the visible ones are ever submitted).
        if ($client) {
            $visibleFields = \App\Models\CustomField::clientFields()
                ->where('admin_only', false)
                ->get()
                ->keyBy('id');

            foreach ($visibleFields as $id => $field) {
                $raw = $request->input("custom_fields.$id");

                $value = is_array($raw) ? implode(', ', array_filter((array) $raw)) : (string) $raw;

                if ($value === '') {
                    \App\Models\CustomFieldValue::where('field_id', $id)->where('rel_id', $client->id)->delete();

                    continue;
                }

                \App\Models\CustomFieldValue::updateOrCreate(
                    ['field_id' => $id, 'rel_id' => $client->id],
                    ['value' => $value]
                );
            }
        }

        return redirect()->route('client.account.profile')
            ->with('success', __('messages.success.profile_updated'));
    }

    public function changePassword()
    {
        return view('client.account.password');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'password_confirmation' => 'required|string',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => __('messages.error.current_password_incorrect')]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // A "remember me" cookie signs its holder in on its own for as long as
        // the token behind it stays put. Changing the password is how somebody
        // ends a session they did not start, so the token goes with it and the
        // cookie stops working - this one included, which is why the current
        // session is re-remembered below.
        $user->setRememberToken(Str::random(60));
        $user->save();

        Auth::guard('web')->login($user, true);

        return redirect()->route('client.account.password')
            ->with('success', __('messages.success.password_changed'));
    }

    public function paymentMethods()
    {
        // Legacy alias for client.payment-methods.index (the nav links there).
        // It used to render an unconditionally empty list.
        return redirect()->route('client.payment-methods.index');
    }

    public function contacts()
    {
        $client = $this->currentClient();
        $contacts = $client ? $client->contacts()->orderBy('id')->get() : collect();

        return view('client.account.contacts', compact('contacts'));
    }

    public function storeContact(Request $request)
    {
        $client = $this->currentClient();

        if (! $client) {
            return redirect()->route('client.account.contacts')
                ->with('error', __('messages.error.client_profile_not_found'));
        }

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
        ]);

        // Only the preferences the form actually asked about. The add form has
        // no boxes for these - they are on the edit form below it - and a box
        // that is not on the form is a box nobody unticked, so writing
        // boolean() for all five made every new contact receive nothing. Left
        // alone, the columns take their own defaults, which is every kind.
        $kinds = ['general_emails', 'product_emails', 'domain_emails', 'invoice_emails', 'support_emails'];
        $preferences = [];

        // A form that asks is answered exactly as it was ticked, unticked boxes
        // included - the same way the edit form below is handled. A form that
        // does not ask at all is not an answer of "none": leave the columns to
        // their own defaults, which is every kind.
        if (array_filter($kinds, fn ($kind) => $request->has($kind))) {
            foreach ($kinds as $kind) {
                $preferences[$kind] = $request->boolean($kind);
            }
        }

        Contact::create($preferences + [
            'client_id' => $client->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'company_name' => $request->company_name,
            'phone_number' => $request->phone_number,
        ]);

        return redirect()->route('client.account.contacts')
            ->with('success', __('messages.success.contact_created'));
    }

    /**
     * Look at a different one of the accounts this login belongs to.
     *
     * Most customers have exactly one and never see this; the client area used
     * to answer with the first account whatever the login was attached to.
     */
    public function switchAccount(Client $client)
    {
        abort_unless(auth()->user()->clients()->whereKey($client->id)->exists(), 403);

        session(['active_client_id' => $client->id]);

        return back()->with('success', __('client.account.account_switched', [
            'name' => trim($client->first_name.' '.$client->last_name),
        ]));
    }

    /** The Edit button used to be a link to nowhere. */
    public function updateContact(Request $request, Contact $contact)
    {
        $client = $this->currentClient();

        abort_if(! $client || $contact->client_id !== $client->id, 403);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
        ]);

        $contact->update($validated + [
            'general_emails' => $request->boolean('general_emails'),
            'product_emails' => $request->boolean('product_emails'),
            'domain_emails' => $request->boolean('domain_emails'),
            'invoice_emails' => $request->boolean('invoice_emails'),
            'support_emails' => $request->boolean('support_emails'),
        ]);

        return redirect()->route('client.account.contacts')
            ->with('success', __('messages.success.contact_updated'));
    }

    public function security()
    {
        $user = auth()->user();
        $twoFactorEnabled = ! empty($user->second_factor_type) && ! empty($user->second_factor_secret);

        // Session listing only works when sessions are stored in the database;
        // with file/redis drivers there is nothing safe to enumerate.
        $sessionsSupported = config('session.driver') === 'database';
        $sessions = $sessionsSupported
            ? DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get()
            : collect();

        $client = $this->currentClient();
        $phoneVerifyAvailable = app(\App\Services\Sms\TwilioVerifyClient::class)->enabled();

        return view('client.account.security', compact('user', 'twoFactorEnabled', 'sessions', 'sessionsSupported', 'client', 'phoneVerifyAvailable'));
    }

    public function logoutSession(string $sessionId)
    {
        if (config('session.driver') !== 'database') {
            abort(404);
        }
        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->delete();

        return back()->with('success', __('client.security.session_revoked'));
    }

    public function destroyContact(Contact $contact)
    {
        $client = $this->currentClient();
        if (! $client || $contact->client_id !== $client->id) {
            abort(403);
        }
        $contact->delete();

        return back()->with('success', __('messages.success.contact_removed'));
    }
}
