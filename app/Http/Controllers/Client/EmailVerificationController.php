<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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
 * Unverified customers can still sign in and look around - they are stopped at
 * the checkout, the one point where an unreachable address actually costs
 * somebody money.
 */
class EmailVerificationController extends Controller
{
    /** How long a link is good for. */
    private const LINK_TTL_MINUTES = 60 * 24;

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
        return URL::temporarySignedRoute(
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
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Verification email could not be sent ('.$user->email.'): '.$e->getMessage());

            return false;
        }
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
     * The link in the mail. No sign-in required: the customer may open it on a
     * phone that has no session. The signature is what makes it safe.
     */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (! $request->hasValidSignature() || ! $user || ! hash_equals(sha1($user->email), $hash)) {
            return redirect()->route('client.login')
                ->with('error', __('client.email_verify.link_invalid'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Somebody else already signed in on this browser keeps their session;
        // otherwise the person who proved the address is let in.
        if (! Auth::check()) {
            Auth::login($user);
        }

        // An order that was stopped at the payment step goes back there - that
        // is what they verified for. The marker lives in the session, so a link
        // opened on another device lands on the dashboard instead, with the
        // basket still waiting.
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
