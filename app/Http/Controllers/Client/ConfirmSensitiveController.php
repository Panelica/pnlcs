<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\SensitiveActionConfirmation;
use Illuminate\Http\Request;

/** The "confirm it is you" page in front of sensitive actions. */
class ConfirmSensitiveController extends Controller
{
    public function show(SensitiveActionConfirmation $confirmation)
    {
        return view('client.auth.confirm-sensitive', [
            'email' => auth()->user()->email,
            'codeSent' => $confirmation->hasPendingCode(auth()->user()),
            'minutes' => SensitiveActionConfirmation::CODE_MINUTES,
        ]);
    }

    public function send(SensitiveActionConfirmation $confirmation)
    {
        $confirmation->sendCode(auth()->user());

        return redirect()->route('client.confirm.show')
            ->with('success', __('client.confirm.sent', ['email' => auth()->user()->email]));
    }

    public function verify(Request $request, SensitiveActionConfirmation $confirmation)
    {
        $request->validate(['code' => 'required|string|max:12']);

        if (! $confirmation->check(auth()->user(), (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('client.confirm.wrong_code')]);
        }

        $return = (string) session()->pull('sensitive_return', '');
        // Only back into this site.
        if ($return === '' || ! str_starts_with($return, url('/'))) {
            $return = route('client.home');
        }

        return redirect()->to($return);
    }
}
