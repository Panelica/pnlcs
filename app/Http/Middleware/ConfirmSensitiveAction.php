<?php

namespace App\Http\Middleware;

use App\Services\SensitiveActionConfirmation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the customer to confirm with an emailed code first, unless they have
 * in the last few minutes (SensitiveActionConfirmation). A GET is returned
 * to afterwards; a form post cannot be replayed, so its page is.
 */
class ConfirmSensitiveAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $confirmation = app(SensitiveActionConfirmation::class);

        if (! $user || $confirmation->confirmed($user)) {
            return $next($request);
        }

        $return = $request->isMethod('GET') ? $request->fullUrl() : url()->previous();
        session(['sensitive_return' => $return]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('client.confirm.required'),
                'confirm_url' => route('client.confirm.show'),
            ], 403);
        }

        return redirect()->route('client.confirm.show');
    }
}
