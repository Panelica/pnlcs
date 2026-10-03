<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Support\CustomerCurrency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Which currency this page shows prices in (CustomerCurrency).
 *
 * A visitor picks one from the top bar with ?currency=EUR, the way ?lang=
 * picks a language, and it is kept for the session. A logged-in customer sees
 * their account's currency and the parameter changes nothing: the account's
 * currency decides what their invoices are billed in, so it is not something
 * a link should be able to change. The admin area is left alone.
 */
class SetDisplayCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nothing carried over from an earlier request in the same process.
        app()->forgetInstance(CustomerCurrency::BINDING);

        if (! $request->is('admin', 'admin/*')) {
            $this->prepare($request);
        }

        return $next($request);
    }

    /**
     * Bind the currency and share what the views need. Never throws: a
     * failure leaves every price in the shop currency.
     */
    private function prepare(Request $request): void
    {
        view()->share('customerCurrencyEnabled', false);
        view()->share('displayCurrency', null);
        view()->share('customerCurrencyChoice', collect());

        try {
            // Off - the default - costs one settings lookup and binds nothing:
            // every price helper then prints the shop currency, as before.
            if (! CustomerCurrency::enabled()) {
                return;
            }

            $client = $this->client();

            if (! $client && $request->hasSession()) {
                $this->remember($request);
            }

            $currency = CustomerCurrency::resolve($request, $client);
            CustomerCurrency::bind($currency);

            view()->share('customerCurrencyEnabled', true);
            view()->share('displayCurrency', $currency);
            view()->share('customerCurrencyChoice', $client
                ? collect()
                : Currency::orderByDesc('is_default')->orderBy('code')->get()->filter(fn ($c) => CustomerCurrency::usable($c))->values());
        } catch (Throwable) {
            // Prices stay in the shop currency; the page still renders.
        }
    }

    /** A visitor's ?currency=XXX, kept when it names a currency that can be used. */
    private function remember(Request $request): void
    {
        $code = $request->query('currency');

        if (! is_string($code) || ! preg_match('/^[A-Za-z]{3}$/', $code)) {
            return;
        }

        $currency = Currency::where('code', strtoupper($code))->first();

        if (CustomerCurrency::usable($currency)) {
            $request->session()->put(CustomerCurrency::SESSION_KEY, $currency->id);
        }
    }

    /** The account a logged-in customer is looking at, as every client page resolves it. */
    private function client(): ?\App\Models\Client
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'clients')) {
            return null;
        }

        $selected = session('active_client_id');
        $client = $selected ? $user->clients()->whereKey($selected)->first() : null;

        return $client ?? $user->clients()->first();
    }
}
