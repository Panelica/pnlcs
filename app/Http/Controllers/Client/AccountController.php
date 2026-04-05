<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function profile()
    {
        $user   = auth()->user();
        $client = $user->clients()->first();
        return view('client.account.profile', compact('user', 'client'));
    }

    public function updateProfile(Request $request)
    {
        $user   = auth()->user();
        $client = $user->clients()->first();

        $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'company_name' => 'nullable|string|max:255',
            'address1'     => 'nullable|string|max:255',
            'address2'     => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'postcode'     => 'nullable|string|max:20',
            'country'      => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:50',
        ]);

        $user->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
        ]);

        if ($client) {
            $client->update([
                'first_name'   => $request->first_name,
                'last_name'    => $request->last_name,
                'email'        => $request->email,
                'company_name' => $request->company_name,
                'address1'     => $request->address1,
                'address2'     => $request->address2,
                'city'         => $request->city,
                'state'        => $request->state,
                'postcode'     => $request->postcode,
                'country'      => $request->country,
                'phone_number' => $request->phone_number,
            ]);
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
            'current_password'      => 'required|string',
            'password'              => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'password_confirmation' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('client.account.password')
            ->with('success', __('messages.success.password_changed'));
    }

    public function paymentMethods()
    {
        $client         = auth()->user()->clients()->first();
        $paymentMethods = collect();
        return view('client.account.payment_methods', compact('paymentMethods'));
    }

    public function contacts()
    {
        $client   = auth()->user()->clients()->first();
        $contacts = $client ? $client->contacts()->orderBy('id')->get() : collect();
        return view('client.account.contacts', compact('contacts'));
    }

    public function storeContact(Request $request)
    {
        $client = auth()->user()->clients()->first();

        if (!$client) {
            return redirect()->route('client.account.contacts')
                ->with('error', __('messages.error.client_profile_not_found'));
        }

        $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
        ]);

        Contact::create([
            'client_id'      => $client->id,
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'email'          => $request->email,
            'company_name'   => $request->company_name,
            'phone_number'   => $request->phone_number,
            'general_emails' => true,
            'product_emails' => false,
            'domain_emails'  => false,
            'invoice_emails' => false,
            'support_emails' => false,
        ]);

        return redirect()->route('client.account.contacts')
            ->with('success', __('messages.success.contact_created'));
    }

    public function security()
    {
        $user = auth()->user();
        $twoFactorEnabled = !empty($user->second_factor_type) && !empty($user->second_factor_secret);
        return view('client.account.security', compact('user', 'twoFactorEnabled'));
    }

    public function destroyContact(\App\Models\Contact $contact)
    {
        $client = auth()->user()->clients()->first();
        if (!$client || $contact->client_id !== $client->id) abort(403);
        $contact->delete();
        return back()->with("success", "Contact removed.");
    }
}