<?php

namespace App\Support;

/**
 * Staff signed in as a customer (Admin\ClientController::impersonate).
 *
 * The customer's own decisions about their money are not made from such a
 * session: storing a card, and agreeing to have it charged automatically, are
 * consent the cardholder gives, and the activity log records them under the
 * customer's name. Staff can still pay an invoice for the customer.
 */
final class Impersonation
{
    public static function active(): bool
    {
        return session()->has('impersonating_admin_id');
    }
}
