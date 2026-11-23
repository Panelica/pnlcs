<?php

namespace App\Services;

/**
 * Asking a registry whether a domain is taken.
 *
 * Kept apart from the search screen so the screen can be tested without a
 * registry on the other end, and so the one place that decides "we could not
 * find out" is the one place that talks to the registry.
 *
 * RDAP is asked first (RdapAvailability): it answers with a status code. A
 * registry without RDAP, or one that did not answer over it, is asked on
 * port 43, and that free-text answer is read by read().
 */
class WhoisLookup
{
    /** Seconds to wait for the connection and for each read. */
    private const TIMEOUT = 5;

    /** Phrases registries use for a name they do not hold. */
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
        // Radix (.online, .site, .store ...) and NIXI (.in)
        'is available for registration',
        // CentralNic (.co, .xyz)
        'The queried object does not exist',
        // nic.at
        'nothing found',
        // DNS-LU
        'No such domain',
    ];

    /**
     * Free-name answers whose spacing varies: EURid (.eu), DNS Belgium and
     * nic.it all write "Status:" and AVAILABLE, separated by spaces or a tab.
     * Anchored to the line, so "Status: NOT AVAILABLE" (.be, taken) does not
     * match.
     */
    private const AVAILABLE_PATTERNS = [
        '/^\s*Status:\s*AVAILABLE\s*$/mi',
    ];

    /**
     * Fields only a registration record carries. With the name itself in the
     * answer, one of these is what makes it a "taken". A rate-limit notice, a
     * refusal or "TLD is not supported" carries none, and is not an answer.
     */
    private const REGISTERED_FIELDS = '/^\s*(registrar|registry domain id|registered(\s+on)?|creat(ed|ion date)|nserver|name\s*servers?|domain status|status|state|holder|registrant|expir\w*(\s+date)?|valid until)\s*:/mi';

    /**
     * @return array{available: bool, checked: bool, response: string}
     */
    public function check(string $domain, ?string $server): array
    {
        $rdap = app(RdapAvailability::class)->check($domain);
        if ($rdap !== null) {
            return $rdap + ['response' => ''];
        }

        if ($server === null || $server === '') {
            return ['available' => false, 'checked' => false, 'response' => ''];
        }

        $response = $this->ask($server, $domain);

        if ($response === null) {
            return ['available' => false, 'checked' => false, 'response' => ''];
        }

        return $this->read($domain, $response) + ['response' => $response];
    }

    /**
     * The registry's port-43 answer, or null when there was none: no
     * connection, or the read timed out.
     */
    protected function ask(string $server, string $domain): ?string
    {
        $conn = @fsockopen($server, 43, $errno, $errstr, self::TIMEOUT);

        if (! $conn) {
            // Not an answer. It used to be read as "available", which put a
            // price and an add-to-cart button next to a name nobody had
            // checked.
            return null;
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

        return $timedOut ? null : $response;
    }

    /**
     * What a port-43 answer says about a name.
     *
     * Free when it uses a phrase a registry uses for a free name; taken when
     * it is the name's registration record; otherwise not an answer. An
     * answer this class did not recognise used to count as "taken", so a
     * registry that words a free name its own way (.eu "Status: AVAILABLE"),
     * a rate-limit notice or a server that does not serve the TLD showed a
     * free name as unavailable.
     *
     * @return array{available: bool, checked: bool}
     */
    public function read(string $domain, string $response): array
    {
        if (trim($response) === '') {
            return ['available' => false, 'checked' => false];
        }

        foreach (self::AVAILABLE_PHRASES as $phrase) {
            if (stripos($response, $phrase) !== false) {
                return ['available' => true, 'checked' => true];
            }
        }

        foreach (self::AVAILABLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $response)) {
                return ['available' => true, 'checked' => true];
            }
        }

        if (stripos($response, strtolower(trim($domain))) !== false && preg_match(self::REGISTERED_FIELDS, $response)) {
            return ['available' => false, 'checked' => true];
        }

        return ['available' => false, 'checked' => false];
    }
}
