<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proving that the address on an account belongs to the person using it.
 *
 * Anyone can type anyone's email into a sign-up form. Everything that reaches
 * a customer afterwards - the invoice, the password reset, the suspension
 * notice - goes to that address, so an unproven one is a service sold to
 * somebody who will never hear from us, and a stranger's inbox filling up with
 * mail about an account they did not open.
 *
 * A signed link rather than a stored token: the signature carries the user id
 * and a hash of the address, so nothing has to be kept in a table and a
 * changed address invalidates every old link on its own.
 *
 * The same mail carries a six-digit code for the page the customer is already
 * signed in on. Customers read the mail on a phone and pass on "the code" -
 * the link included - to whoever asked them for it, so neither one signs
 * anybody in: they prove the mailbox, and the session proves the person.
 *
 * Unverified customers can still sign in and look around - they are stopped at
 * the checkout, the one point where an unreachable address actually costs
 * somebody money.
 */
class EmailVerificationController extends Controller
{
    /** How long a link is good for. */
    private const LINK_TTL_MINUTES = 60 * 24;

    /** How long a code is good for, and how many wrong tries it takes. */
    public const CODE_MINUTES = 15;

    public const CODE_ATTEMPTS = 5;

    /**
     * Whether this installation asks for verification at all.
     *
     * On by default: an operator who never opens the settings screen gets the
     * safer behaviour, and the one who does not want it can say so.
     */
    public static function required(): bool
    {
        return (string) Setting::get('EmailVerificationRequired', '1') === '1';
    }

    public static function verificationUrl(User $user): string
    {
        // Signed under the configured address, not the request's: see MailLink.
        return MailLink::signedRoute(
            'client.verification.verify',
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    /**
     * Send the link. Never throws: a mail server having a bad afternoon must
     * not take the sign-up down with it - the account exists, and the customer
     * finds a "send it again" button waiting for them.
     */
    public static function send(User $user): bool
    {
        if (! self::required() || $user->hasVerifiedEmail()) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new EmailVerificationMail(
                self::verificationUrl($user),
                $user->email,
                (string) $user->first_name,
                self::newCode($user),
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Verification email could not be sent ('.$user->email.'): '.$e->getMessage());

            return false;
        }
    }

    /**
     * A fresh code; the one sent before stops working. Kept in the cache
     * rather than the session: staff changing a login's address send the mail
     * from their own session, and the customer types the code into theirs.
     * Only a hash is kept, with the address it was sent to.
     */
    private static function newCode(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(self::codeKey($user), [
            'hash' => hash('sha256', $code),
            'email' => sha1($user->email),
            'attempts' => 0,
            'expires' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::CODE_MINUTES));

        return $code;
    }

    /** True when the code is the one last sent to the user's current address. */
    private static function checkCode(User $user, string $code): bool
    {
        $pending = Cache::get(self::codeKey($user));

        if (! is_array($pending) || $pending['expires'] < now()->getTimestamp()
            || $pending['attempts'] >= self::CODE_ATTEMPTS
            || ! hash_equals($pending['email'], sha1($user->email))) {
            return false;
        }

        if (! hash_equals($pending['hash'], hash('sha256', $code))) {
            $pending['attempts']++;
            Cache::put(self::codeKey($user), $pending, now()->setTimestamp($pending['expires']));

            return false;
        }

        Cache::forget(self::codeKey($user));

        return true;
    }

    private static function codeKey(User $user): string
    {
        return 'email-verification-code:'.$user->id;
    }

    /** The "check your inbox" page. */
    public function notice()
    {
        if (! self::required() || Auth::user()?->hasVerifiedEmail()) {
            return redirect()->route('client.home');
        }

        return view('client.auth.verify-email');
    }

    /**
     * The code in the mail, typed into the waiting page.
     */
    public function verifyCode(Request $request)
    {
        $user = $request->user();

        if (! self::required() || $user->hasVerifiedEmail()) {
            return redirect()->route('client.home');
        }

        $code = preg_replace('/\D/', '', (string) $request->input('code'));

        if ($code === '' || ! self::checkCode($user, $code)) {
            return back()->withErrors(['code' => __('client.email_verify.code_invalid')]);
        }

        $user->markEmailAsVerified();

        return $this->afterVerification($request);
    }

    /**
     * The link in the mail. No sign-in required: the customer may open it on a
     * phone that has no session. The signature is what makes it safe.
     *
     * It verifies the address and nothing more. It used to sign the holder in
     * as well, for 24 hours and as often as it was opened - and customers
     * forward it, taking it for "the code".
     */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        // Both signatures: a link signed relatively (what MailLink builds now)
        // and one signed absolutely (what is already in customers' mailboxes).
        $signed = $request->hasValidSignature(false) || $request->hasValidSignature();

        if (! $signed || ! $user || ! hash_equals(sha1($user->email), $hash)) {
            return redirect()->route('client.login')
                ->with('error', __('client.email_verify.link_invalid'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            Cache::forget(self::codeKey($user));
        }

        // Opened where nobody is signed in - another device, a forwarded
        // link - the address is confirmed and the customer signs in as usual.
        if (! Auth::check()) {
            return redirect()->route('client.login')
                ->with('success', __('client.email_verify.verified_sign_in'));
        }

        return $this->afterVerification($request);
    }

    private function afterVerification(Request $request)
    {
        // An order that was stopped at the payment step goes back there - that
        // is what they verified for. The marker lives in the session, so a
        // browser without it lands on the dashboard instead, with the basket
        // still waiting.
        $target = $request->session()->pull('checkout_after_verification')
            ? 'client.cart.checkout'
            : 'client.home';

        return redirect()->route($target)->with('success', __('client.email_verify.verified'));
    }

    /** The "send it again" button. */
    public function resend(Request $request)
    {
        $user = $request->user();

        if (! self::required() || $user->hasVerifiedEmail()) {
            return redirect()->route('client.home');
        }

        if (! self::send($user)) {
            return back()->with('error', __('client.email_verify.send_failed'));
        }

        return back()->with('success', __('client.email_verify.sent', ['email' => $user->email]));
    }
}
