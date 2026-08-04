<?php

namespace App\Services;

/**
 * Asking a registry whether a domain is taken.
 *
 * Kept apart from the search screen so the screen can be tested without a
 * registry on the other end, and so the one place that decides "we could not
 * find out" is the one place that talks to the socket.
 */
class WhoisLookup
{
    /** Seconds to wait for the connection and for each read. */
    private const TIMEOUT = 5;

    private const AVAILABLE_PHRASES = [
        'No match for',
        'NOT FOUND',
        'No Data Found',
        'Status: free',
        'No entries found',
        'not found',
        'is free',
        'No match',
        'Object not found',
        'No information available',
        'Domain not found',
        'This domain is available',
    ];

    /**
     * @return array{available: bool, checked: bool, response: string}
     */
    public function check(string $domain, ?string $server): array
    {
        if ($server === null || $server === '') {
            return ['available' => false, 'checked' => false, 'response' => ''];
        }

        $conn = @fsockopen($server, 43, $errno, $errstr, self::TIMEOUT);

        if (! $conn) {
            // Not an answer. It used to be read as "available", which put a
            // price and an add-to-cart button next to a name nobody had
            // checked.
            return ['available' => false, 'checked' => false, 'response' => ''];
        }

        // Without this a half-open connection blocks in fgets; the loop below
        // only gets to look at the clock between reads.
        stream_set_timeout($conn, self::TIMEOUT);

        fwrite($conn, $domain."\r\n");

        $response = '';
        $deadline = microtime(true) + self::TIMEOUT;

        while (! feof($conn) && microtime(true) < $deadline) {
            $line = fgets($conn, 1024);

            if ($line === false) {
                break;
            }

            $response .= $line;
        }

        $timedOut = stream_get_meta_data($conn)['timed_out'] ?? false;

        fclose($conn);

        if ($response === '' || $timedOut) {
            return ['available' => false, 'checked' => false, 'response' => $response];
        }

        foreach (self::AVAILABLE_PHRASES as $phrase) {
            if (stripos($response, $phrase) !== false) {
                return ['available' => true, 'checked' => true, 'response' => $response];
            }
        }

        return ['available' => false, 'checked' => true, 'response' => $response];
    }
}
