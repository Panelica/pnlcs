<?php

namespace Modules\CompanyLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;

/**
 * GUS Baza Internetowa REGON (BIR 1.1) — SOAP 1.2 + WS-Addressing.
 *
 * The primary identification source: name, NIP, REGON, address, legal form,
 * PKD. The service is stateful: Zaloguj opens a session, DaneSzukajPodmioty
 * runs the search, Wyloguj closes it. The session id returned by Zaloguj is
 * sent as the `sid` HTTP header on subsequent calls. The API key must never
 * leave the backend.
 */
final class GusCompanyProvider implements CompanyDataProviderInterface
{
    private const NS = 'http://CIS/BIR/PUBL/2014/07';
    private const DAT = 'http://CIS/BIR/PUBL/2014/07/DataContract';
    private const WSA = 'http://www.w3.org/2005/08/addressing';

    private ?string $sid = null;

    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $apiKey,
        private readonly int $connectTimeout,
        private readonly int $requestTimeout,
    ) {
    }

    public function findByNip(string $nip): ?CompanyData
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw new ProviderException('GUS: no API key configured', ProviderException::NOT_CONFIGURED);
        }

        $this->sid = $this->login();

        try {
            $raw = $this->search($nip);

            return $this->parse($raw);
        } finally {
            $this->logout();
        }
    }

    /**
     * Verify the credentials and reach the GUS BIR endpoint.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            return ['success' => false, 'message' => __('messages.company_lookup.test_no_key')];
        }

        try {
            $this->sid = $this->login();
            $this->logout();

            return ['success' => true, 'message' => __('messages.company_lookup.test_ok')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function login(): string
    {
        $body = $this->envelope('Zaloguj', '<ns:pKluczUzytkownika>'.htmlspecialchars($this->apiKey, ENT_XML1).'</ns:pKluczUzytkownika>');
        $xml = $this->call('Zaloguj', $body);

        if (! preg_match('#<ZalogujResult[^>]*>(.*?)</ZalogujResult>#s', $xml, $m) || trim($m[1]) === '') {
            throw new ProviderException('GUS: login failed (no session id)', ProviderException::GUS_ERROR);
        }

        return trim($m[1]);
    }

    private function search(string $nip): string
    {
        $inner = '<ns:pParametryWyszukiwania>'
            .'<dat:Nip>'.htmlspecialchars($nip, ENT_XML1).'</dat:Nip>'
            .'</ns:pParametryWyszukiwania>';

        return $this->call('DaneSzukajPodmioty', $this->envelope('DaneSzukajPodmioty', $inner));
    }

    private function logout(): void
    {
        if ($this->sid === null) {
            return;
        }

        try {
            $body = $this->envelope('Wyloguj', '<ns:pIdentyfikatorSesji>'.htmlspecialchars($this->sid, ENT_XML1).'</ns:pIdentyfikatorSesji>');
            $this->call('Wyloguj', $body);
        } catch (ProviderException) {
            // A failed logout must not mask the lookup result.
        }
    }

    private function envelope(string $operation, string $inner): string
    {
        $action = self::NS.'/IUslugaBIRzewnPubl/'.$operation;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="'.self::NS.'" xmlns:dat="'.self::DAT.'">'
            .'<soap:Header xmlns:wsa="'.self::WSA.'"><wsa:To>'.$this->endpoint.'</wsa:To><wsa:Action>'.$action.'</wsa:Action></soap:Header>'
            .'<soap:Body><ns:'.$operation.'>'.$inner.'</ns:'.$operation.'></soap:Body>'
            .'</soap:Envelope>';
    }

    private function call(string $operation, string $body): string
    {
        $action = self::NS.'/IUslugaBIRzewnPubl/'.$operation;

        try {
            $http = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->withHeaders([
                    'Content-Type' => 'application/soap+xml; charset=utf-8; action="'.$action.'"',
                ]);

            if ($this->sid !== null) {
                $http = $http->withHeaders(['sid' => $this->sid]);
            }

            $response = $http->withBody($body, 'application/soap+xml')->send('POST', $this->endpoint);
        } catch (ConnectionException $e) {
            throw new ProviderException('GUS: connection failed — '.$e->getMessage(), ProviderException::API_TIMEOUT);
        }

        if ($response->status() === 429) {
            throw new ProviderException('GUS: rate limited', ProviderException::RATE_LIMIT);
        }

        if ($response->failed()) {
            throw new ProviderException('GUS: HTTP '.$response->status(), ProviderException::GUS_ERROR);
        }

        $xml = $response->body();
        if (str_contains($xml, 'Fault')) {
            throw new ProviderException('GUS: SOAP fault — '.$this->faultMessage($xml), ProviderException::GUS_ERROR);
        }

        return $xml;
    }

    private function faultMessage(string $xml): string
    {
        if (preg_match('#<faultstring[^>]*>(.*?)</faultstring>#s', $xml, $m)) {
            return trim($m[1]);
        }

        if (preg_match('#<[^>]*Text[^>]*>(.*?)</[^>]*Text>#s', $xml, $m)) {
            return trim($m[1]);
        }

        return 'unknown';
    }

    private function parse(string $xml): ?CompanyData
    {
        // DaneSzukajPodmiotyResult carries the <root><dane>… document as an
        // HTML-escaped string; decode it before parsing.
        if (preg_match('#<DaneSzukajPodmiotyResult[^>]*>(.*?)</DaneSzukajPodmiotyResult>#s', $xml, $m)) {
            $payload = trim($m[1]);
        } else {
            $payload = $xml;
        }

        if ($payload === '') {
            return null;
        }

        $payload = html_entity_decode($payload, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // An empty search result means "nothing found".
        if (trim(strip_tags($payload)) === '' || ! str_contains($payload, '<dane>')) {
            return null;
        }

        $fields = $this->flatten($payload);

        // Nothing to identify the entity by → not found, not an error.
        if (empty($fields['nip']) && empty($fields['regon']) && empty($fields['nazwa'])) {
            return null;
        }

        $data = new CompanyData();
        $data->name = $fields['nazwa'] ?? $fields['nazwapodmiotu'] ?? null;
        $data->nip = $fields['nip'] ?? null;
        $data->regon = $fields['regon'] ?? null;

        $data->country = $fields['adsiedzkraj'] ?? $fields['kraj'] ?? null;
        $data->voivodeship = $fields['adsiedzwojewodztwo'] ?? $fields['wojewodztwo'] ?? null;
        $data->street = $fields['adsiedzulica'] ?? $fields['ulica'] ?? null;
        $data->buildingNumber = $fields['adsiedznrnieruchomosci'] ?? $fields['nrnieruchomosci'] ?? $fields['numerbudynku'] ?? null;
        $data->apartmentNumber = $fields['adsiedznrlokalu'] ?? $fields['nrlokalu'] ?? $fields['numerlokalu'] ?? null;
        $data->postalCode = $fields['adsiedzKodPocztowy'] ?? $fields['adsiedzkodpocztowy'] ?? $fields['kodpocztowy'] ?? null;
        $data->city = $fields['adsiedzmiejscowosc'] ?? $fields['adsiedzmiejscowoscnazwa'] ?? $fields['miejscowosc'] ?? null;

        $data->legalForm = $fields['formaprawna'] ?? $fields['nazwaformyprawnej'] ?? null;

        $data->pkd = $this->pkdCodes($fields);

        return $data;
    }

    /**
     * Flatten XML into a case-insensitive map of local tag name → text.
     *
     * @return array<string, ?string>
     */
    private function flatten(string $xml): array
    {
        $out = [];

        $doc = new \DOMDocument();
        if (! @$doc->loadXML($xml)) {
            return $out;
        }

        $walker = function (\DOMNode $node) use (&$walker, &$out) {
            if ($node instanceof \DOMElement) {
                $name = strtolower($node->localName ?: $node->nodeName);
                $text = trim((string) $node->textContent);
                if ($text !== '') {
                    if (! isset($out[$name])) {
                        $out[$name] = $text;
                    }

                    // BIR1 prefixes every field with the entity wrapper name
                    // (fizyczna_… for natural persons, prawne_… for companies),
                    // so also index the stripped form: fizyczna_Nazwa → nazwa.
                    $stripped = preg_replace('/^(fizyczna_|prawne_)/', '', $name);
                    if ($stripped !== $name && ! isset($out[$stripped])) {
                        $out[$stripped] = $text;
                    }
                }
            }
            foreach ($node->childNodes as $child) {
                $walker($child);
            }
        };

        $walker($doc->documentElement);

        return $out;
    }

    /**
     * @param  array<string, ?string>  $fields
     * @return array<int, string>
     */
    private function pkdCodes(array $fields): array
    {
        foreach (['pkd', 'rodzajdzialalnosci', 'pkdprzewazajace'] as $key) {
            if (isset($fields[$key]) && $fields[$key] !== null) {
                $parts = array_filter(array_map('trim', preg_split('/[\s,;]+/', $fields[$key]) ?: []));

                return array_values($parts);
            }
        }

        return [];
    }
}
