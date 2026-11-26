<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Sms\WhatsappPhoneVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The WhatsApp Cloud API webhook, which today only carries phone
 * verification codes customers send us.
 *
 * GET is Meta's subscription handshake: hub.mode=subscribe, the verify token
 * the operator typed on both sides, and a challenge to echo back.
 * POST is a notification, signed in X-Hub-Signature-256 with the app secret
 * over the raw body; anything unsigned or signed with another secret is
 * refused before it is read. A signed notification is always answered 200,
 * whatever it held, or Meta retries it for days.
 */
class WhatsappWebhookController extends Controller
{
    public function verify(Request $request, WhatsappPhoneVerifier $whatsapp): Response
    {
        abort_unless($whatsapp->enabled(), 404);

        // PHP turns the dots of hub.mode into underscores in $_GET.
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expected = (string) Setting::get('WhatsappWebhookVerifyToken', '');

        abort_unless($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token), 403);

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsappPhoneVerifier $whatsapp): Response
    {
        abort_unless($whatsapp->enabled(), 404);

        $secret = (string) Setting::get('WhatsappAppSecret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($secret !== '' && hash_equals($expected, $signature), 403);

        $business = $whatsapp->businessNumber();

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $value = (array) ($change['value'] ?? []);

                // An app can serve several numbers; only ours verifies.
                $to = preg_replace('/\D+/', '', (string) ($value['metadata']['display_phone_number'] ?? ''));
                if ($to !== '' && $to !== $business) {
                    continue;
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if (($message['type'] ?? null) !== 'text') {
                        continue;
                    }
                    $whatsapp->receive((string) ($message['from'] ?? ''), (string) ($message['text']['body'] ?? ''));
                }
            }
        }

        return response('', 200);
    }
}
