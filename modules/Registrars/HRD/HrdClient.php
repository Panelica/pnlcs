<?php

namespace Modules\Registrars\HRD;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Minimal client for the HRD (hrd.pl / api.hrd.pl) registrar API.
 *
 * HRD speaks a binary XML protocol over an SSL socket: every request is the
 * XML document prefixed with a SHA-512 signature (computed from the XML and a
 * binary API hash) and a 4-byte big-endian length. The response is a length
 * prefix followed by an XML document.
 *
 * This is a self-contained reimplementation of the subset of the official
 * hrd/hrd-api SDK that the registrar module needs, so the panel carries no
 * third-party dependency and the networking is reviewable in the module.
 */
class HrdClient
{
    public const PERSON = 'person';
    public const COMPANY = 'company';

    protected const HOST = 'api.hrd.pl';
    protected const PORT = 9999;
    protected const TIMEOUT = 15;
    protected const NS = 'http://api.hrd.pl/api/';

    /** @var resource|null */
    protected $fp = null;

    protected string $binaryHash;

    public function __construct(
        protected string $login,
        protected string $pass,
        string $hashHex,
    ) {
        $this->binaryHash = (string) hex2bin($hashHex);
    }

    /**
     * Authenticate against the API. Must be called once before any other
     * request; the returned token is not needed for signing, but the login
     * call itself establishes the session.
     */
    public function login(): void
    {
        [$dom, $elem] = $this->document('login');
        $elem->appendChild($dom->createElement('login', $this->login));
        $elem->appendChild($dom->createElement('pass', $this->pass));
        $elem->appendChild($dom->createElement('type', 'partnerApi'));

        $xpath = $this->send($dom);

        $token = $xpath->query('/api/token/text()');
        if ($token->length > 0) {
            // Retained for parity with the SDK; not required for signing.
        }
    }

    public function partnerGetBalance(): array
    {
        [$dom] = $this->document('partner', 'getBalance');

        return $this->arrayResponse($this->send($dom), '/api/partner/getBalance/*');
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<string, string>  domain => state (available|taken|unknown|createOnly)
     */
    public function domainCheck(array $domains): array
    {
        [$dom, $elem] = $this->document('domain', 'check');
        foreach ($domains as $domain) {
            $elem->appendChild($dom->createElement('name', $this->idn($domain)));
        }

        $xpath = $this->send($dom);

        $out = [];
        foreach ($xpath->query('/api/domain/check/name') as $item) {
            $attr = $item->attributes->item(0);
            $out[$item->textContent] = $attr ? $attr->textContent : '';
        }

        return $out;
    }

    public function domainInfo(string $domain): array
    {
        [$dom, $elem] = $this->document('domain', 'info');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));

        return $this->arrayResponse($this->send($dom), '/api/domain/info/*', '/api/domain/info/ns/');
    }

    public function domainCreate(string $domain, int $user, array $ns, int $period, bool $privacyProtect = false): int
    {
        [$dom, $elem] = $this->document('domain', 'create');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));
        $elem->appendChild($dom->createElement('user', (string) $user));
        $elem->appendChild($dom->createElement('period', (string) $period));
        $elem->appendChild($dom->createElement('privacyProtect', $privacyProtect ? 'true' : 'false'));
        $this->appendNs($dom, $elem, $ns);

        return $this->intResponse($this->send($dom), '/api/domain/create/actionId');
    }

    public function domainTransfer(string $domain, int $user, string $pw, int $period = 0): int
    {
        [$dom, $elem] = $this->document('domain', 'transfer');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));
        $elem->appendChild($dom->createElement('user', (string) $user));
        if ($period > 0) {
            $elem->appendChild($dom->createElement('period', (string) $period));
        }

        $pwElement = $dom->createElement('pw');
        $pwElement->appendChild($dom->createCDATASection($pw));
        $elem->appendChild($pwElement);

        return $this->intResponse($this->send($dom), '/api/domain/transfer/actionId');
    }

    public function domainRenew(string $domain, string $expiry, int $period): int
    {
        [$dom, $elem] = $this->document('domain', 'renew');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));
        $elem->appendChild($dom->createElement('currentExpirationDate', $expiry));
        $elem->appendChild($dom->createElement('period', (string) $period));

        return $this->intResponse($this->send($dom), '/api/domain/renew/actionId');
    }

    public function domainUpdate(string $domain, array $ns): ?int
    {
        [$dom, $elem] = $this->document('domain', 'update');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));
        $this->appendNs($dom, $elem, $ns);

        return $this->intOrVoidResponse($this->send($dom), '/api/domain/update', '/api/domain/update/actionId');
    }

    public function domainTradeGetPw(string $domain): string
    {
        [$dom, $elem] = $this->document('domain', 'tradeGetPw');
        $elem->appendChild($dom->createElement('name', $this->idn($domain)));

        return $this->stringResponse($this->send($dom), '/api/domain/tradeGetPw/pw');
    }

    public function userCreate(
        string $type,
        string $idNumber,
        string $email,
        ?string $mobilePhone,
        string $landlinePhone,
        ?string $fax,
        string $name,
        string $street,
        string $postcode,
        string $city,
        string $country,
        ?string $representative = null,
    ): int {
        [$dom, $elem] = $this->document('user', 'create');

        match ($type) {
            self::PERSON => $elem->appendChild($dom->createElement('personType')),
            self::COMPANY => $elem->appendChild($dom->createElement('companyType')),
            default => throw new RuntimeException('HRD: invalid registrant type'),
        };

        $elem->appendChild($dom->createElement('idNumber', $idNumber));
        $elem->appendChild($dom->createElement('email', $email));
        if ($mobilePhone !== null) {
            $elem->appendChild($dom->createElement('mobilePhone', $mobilePhone));
        }
        $elem->appendChild($dom->createElement('landlinePhone', $landlinePhone));
        if ($fax !== null) {
            $elem->appendChild($dom->createElement('fax', $fax));
        }
        $elem->appendChild($dom->createElement('name', $name));
        $elem->appendChild($dom->createElement('street', $street));
        $elem->appendChild($dom->createElement('postcode', $postcode));
        $elem->appendChild($dom->createElement('city', $city));
        $elem->appendChild($dom->createElement('country', $country));
        if ($representative !== null && $type === self::COMPANY) {
            $elem->appendChild($dom->createElement('representative', $representative));
        }

        return $this->intResponse($this->send($dom), '/api/user/create/id');
    }

    // ---------------------------------------------------------------------
    // Transport
    // ---------------------------------------------------------------------

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    protected function document(string $action, ?string $sub = null): array
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $api = $dom->createElementNS(self::NS, 'api');
        $elem = $dom->createElement($action);
        if ($sub !== null) {
            $subElem = $dom->createElement($sub);
            $elem->appendChild($subElem);
        }
        $api->appendChild($elem);
        $dom->appendChild($api);

        return $sub !== null ? [$dom, $subElem] : [$dom, $elem];
    }

    protected function send(DOMDocument $dom): DOMXPath
    {
        $this->connect();

        $xml = $dom->saveXML();
        $payload = hash('sha512', $xml.$this->binaryHash, true).$xml;
        $payload = pack('N', strlen($payload)).$payload;

        if (@fwrite($this->fp, $payload) !== strlen($payload)) {
            throw new RuntimeException('HRD: send error');
        }

        $data = $this->readExact(unpack('N', $this->readExact(4))[1]);

        $response = new DOMDocument();
        if (! @$response->loadXML(str_replace('xmlns="'.self::NS.'"', '', $data))) {
            throw new RuntimeException('HRD: malformed response');
        }

        $xpath = new DOMXPath($response);

        $error = $xpath->query('/api/message');
        if ($error->length > 0) {
            throw new RuntimeException($error->item(0)->textContent);
        }

        return $xpath;
    }

    protected function connect(): void
    {
        if (is_resource($this->fp)) {
            return;
        }

        // The endpoint presents a valid public certificate, so verify it. A
        // client that accepts any certificate would happily hand the registrar
        // credentials to a man in the middle.
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $fp = @stream_socket_client('ssl://'.self::HOST.':'.self::PORT, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);

        if ($fp === false) {
            throw new RuntimeException('HRD: connect error '.($errstr ?: $errno));
        }

        $this->fp = $fp;
    }

    protected function readExact(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($this->fp, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('HRD: read error');
            }
            $data .= $chunk;
        }

        return $data;
    }

    // ---------------------------------------------------------------------
    // Response helpers
    // ---------------------------------------------------------------------

    protected function intResponse(DOMXPath $xpath, string $path): int
    {
        $result = $xpath->query($path);
        if ($result->length === 0) {
            throw new RuntimeException('HRD: unexpected response');
        }

        return (int) $result->item(0)->textContent;
    }

    protected function intOrVoidResponse(DOMXPath $xpath, string $voidPath, string $intPath): ?int
    {
        if ($xpath->query($voidPath)->length === 0) {
            throw new RuntimeException('HRD: unexpected response');
        }

        $int = $xpath->query($intPath);

        return $int->length === 1 ? (int) $int->item(0)->textContent : null;
    }

    protected function stringResponse(DOMXPath $xpath, string $path): string
    {
        $result = $xpath->query($path);
        if ($result->length === 0) {
            throw new RuntimeException('HRD: unexpected response');
        }

        return $result->item(0)->textContent;
    }

    protected function arrayResponse(DOMXPath $xpath, string $path, ?string $nsPrefix = null): array
    {
        $result = $xpath->query($path);
        if ($result->length === 0) {
            throw new RuntimeException('HRD: unexpected response');
        }

        $out = [];
        foreach ($result as $item) {
            if ($nsPrefix !== null && $item->nodeName === 'ns') {
                $this->parseNs($out, $xpath, $nsPrefix);
            } else {
                $out[$item->nodeName] = $item->textContent;
            }
        }

        return $out;
    }

    protected function parseNs(array &$out, DOMXPath $xpath, string $prefix): void
    {
        $out['ns'] = [];

        $group = $xpath->query($prefix.'group');
        if ($group->length === 1) {
            $out['ns']['group'] = (int) $group->item(0)->textContent;

            return;
        }

        foreach ($xpath->query($prefix.'ns/ns') as $item) {
            $ipv4 = [];
            $ipv6 = [];
            foreach ($xpath->query('./ipv4/text()', $item) as $ip) {
                $ipv4[] = $ip->textContent;
            }
            foreach ($xpath->query('./ipv6/text()', $item) as $ip) {
                $ipv6[] = $ip->textContent;
            }

            $name = $xpath->query('./name/text()', $item);

            $out['ns'][] = [
                'name' => $name->length ? $name->item(0)->textContent : '',
                'ipv4' => $ipv4,
                'ipv6' => $ipv6,
            ];
        }
    }

    // ---------------------------------------------------------------------
    // Request helpers
    // ---------------------------------------------------------------------

    protected function appendNs(DOMDocument $dom, DOMElement $parent, array $ns): void
    {
        $nsElem = $dom->createElement('ns');

        if (isset($ns['group'])) {
            $nsElem->appendChild($dom->createElement('group', (string) $ns['group']));
        } else {
            $outer = $dom->createElement('ns');
            foreach ($ns as $item) {
                $inner = $dom->createElement('ns');
                $inner->appendChild($dom->createElement('name', $this->idn((string) ($item['name'] ?? ''))));
                foreach ((array) ($item['ipv4'] ?? []) as $ip) {
                    $inner->appendChild($dom->createElement('ipv4', (string) $ip));
                }
                foreach ((array) ($item['ipv6'] ?? []) as $ip) {
                    $inner->appendChild($dom->createElement('ipv6', (string) $ip));
                }
                $outer->appendChild($inner);
            }
            $nsElem->appendChild($outer);
        }

        $parent->appendChild($nsElem);
    }

    protected function idn(string $name): string
    {
        if (! function_exists('idn_to_ascii')) {
            return $name;
        }

        $ascii = @idn_to_ascii($name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? $name : $ascii;
    }
}
