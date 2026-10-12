<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;

/**
 * What a call to another server that ended without an answer says about the call.
 *
 * Laravel turns every transfer that ends without a response into a
 * ConnectionException: a host that cannot be found or reached, and a request
 * that went out and was never answered. Only the second can have done its work
 * on the other side, so only there does "try again" risk doing it twice.
 */
final class ConnectionFailure
{
    /** Whether the request reached the other server before the call failed. */
    public static function requestWasSent(ConnectionException $e): bool
    {
        // cURL counts the bytes of the request it sent. None means no
        // connection was ever made, whatever the error says.
        $previous = $e->getPrevious();
        if ($previous !== null && method_exists($previous, 'getHandlerContext')) {
            $context = (array) $previous->getHandlerContext();
            if (array_key_exists('request_size', $context)) {
                return (int) $context['request_size'] > 0;
            }
        }

        // Without cURL's figures, its message: a transfer that timed out after
        // the connection was made, or a server that closed it without a reply.
        $message = $e->getMessage();

        return str_contains($message, 'Operation timed out') || str_contains($message, 'Empty reply from server');
    }
}
