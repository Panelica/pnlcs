<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Sms\TwilioVerifyClient;
use Illuminate\Http\Request;

/**
 * Verifying the phone number on the account through Twilio Verify. Twilio
 * holds all the state - the code, the attempts, the ten-minute expiry - so
 * there is nothing to store here except the moment a check comes back
 * approved. The routes exist only while the operator has switched the
 * feature on.
 */
class PhoneVerificationController extends Controller
{
    use \App\Http\Controllers\Concerns\ResolvesClient;

    public function start(TwilioVerifyClient $twilio)
    {
        abort_unless($twilio->enabled(), 404);

        $client = $this->currentClient();
        $to = TwilioVerifyClient::e164($client?->full_phone);
        if (! $to) {
            return back()->withErrors(['phone' => __('client.phone_verify.no_phone')]);
        }

        if ($twilio->start($to) === null) {
            return back()->withErrors(['phone' => __('client.phone_verify.send_failed')]);
        }

        return back()->with('phone_code_sent', true)->with('success', __('client.phone_verify.code_sent'));
    }

    public function check(Request $request, TwilioVerifyClient $twilio)
    {
        abort_unless($twilio->enabled(), 404);

        $request->validate(['code' => 'required|string|min:4|max:10']);

        $client = $this->currentClient();
        $to = TwilioVerifyClient::e164($client?->full_phone);
        if (! $to) {
            return back()->withErrors(['phone' => __('client.phone_verify.no_phone')]);
        }

        $result = $twilio->check($to, $request->input('code'));
        if ($result === null) {
            return back()->with('phone_code_sent', true)->withErrors(['code' => __('client.phone_verify.send_failed')]);
        }
        if (! $result['approved']) {
            return back()->with('phone_code_sent', true)->withErrors(['code' => __('client.phone_verify.wrong_code')]);
        }

        $client->forceFill(['phone_verified_at' => now()])->save();

        return back()->with('success', __('client.phone_verify.verified'));
    }
}
