<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Contracts\PhoneVerifier;
use App\Services\Sms\TwilioVerifyClient;
use App\Services\Sms\WhatsappPhoneVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verifying the phone number on the account through Twilio Verify. Twilio
 * holds all the state - the code, the attempts, the ten-minute expiry - so
 * there is nothing to store here except the moment a check comes back
 * approved. The routes exist only while the operator has switched the
 * feature on.
 *
 * WhatsApp works the other way round: the customer is given a code to send
 * us, and the webhook (WhatsappWebhookController) verifies the phone when it
 * arrives from the number on the profile.
 */
class PhoneVerificationController extends Controller
{
    use \App\Http\Controllers\Concerns\ResolvesClient;

    public function start(PhoneVerifier $twilio)
    {
        abort_unless($twilio->enabled(), 404);

        $client = $this->currentClient();
        $to = TwilioVerifyClient::e164($client?->full_phone);
        if (! $to) {
            return back()->withErrors(['phone' => __('client.phone_verify.no_phone')]);
        }

        $started = $twilio->start($to);
        if ($started === null) {
            return back()->withErrors(['phone' => __('client.phone_verify.send_failed')]);
        }
        if (($started['status'] ?? null) === 'too_many_sends') {
            return back()->with('phone_code_sent', true)->withErrors(['phone' => __('client.phone_verify.too_many')]);
        }

        return back()->with('phone_code_sent', true)->with('success', __('client.phone_verify.code_sent'));
    }

    public function check(Request $request, PhoneVerifier $twilio)
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

    /** A code for the customer to send us on WhatsApp (see WhatsappPhoneVerifier). */
    public function whatsappStart(WhatsappPhoneVerifier $whatsapp): RedirectResponse
    {
        abort_unless($whatsapp->enabled(), 404);

        $client = $this->currentClient();
        if (! $client || $whatsapp->start($client) === null) {
            return back()->withErrors(['phone' => __('client.phone_verify.no_phone')]);
        }

        return back();
    }

    /** What the security page polls while it waits for the message. */
    public function whatsappStatus(WhatsappPhoneVerifier $whatsapp): JsonResponse
    {
        abort_unless($whatsapp->enabled(), 404);

        $client = $this->currentClient();

        return response()->json([
            'verified' => (bool) $client?->phone_verified_at,
            'state' => $client ? ($whatsapp->pending($client)['state'] ?? null) : null,
        ]);
    }
}
