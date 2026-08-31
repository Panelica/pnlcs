<?php

namespace Modules\CompanyLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\CompanyLookup\Contracts\CompanyDataProviderInterface;
use Modules\CompanyLookup\Exceptions\ProviderException;

/**
 * GUS Baza Internetowa REGON (BIR) — SOAP.
 *
 * The primary identification source: name, NIP, REGON, address, legal form,
 * PKD. The service is stateful: Zaloguj opens a session, DaneSzukajPodmioty
 * runs the search, Wyloguj closes it. The API key must never leave the
 * backend.
 *
 * The protocol follows the published BIR1 (2014/7) contract. The response is
 * parsed defensively — field names are matched by local name, so namespace
 * and structure changes in the register degrade to fewer fields rather than
 * to a crash.
 */
final class GusCompanyProvider implements CompanyDataProviderInterface
{
    private const NS = 'http://CIS/BIR/PUBL/2014/7';

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
            throw new ProviderException('GUS: no API key configured', ProviderException::GUS_ERROR);
        }

        $sid = $this->login();

        try {
            $raw = $this->search($sid, $nip);

            return $this->parse($raw);
        } finally {
            $this->logout($sid);
        }
    }

    private function login(): string
    {
        $body = $this->envelope('Zaloguj', '<pKluczUzytkownika>'.htmlspecialchars($this->apiKey, ENT_XML1).'</pKluczUzytkownika>');
        $xml = $this->call('Zaloguj', $body);

        if (! preg_match('#<ZalogujResult[^>]*>(.*?)</ZalogujResult>#s', $xml, $m) || trim($m[1]) === '') {
            throw new ProviderException('GUS: login failed (no session id)', ProviderException::GUS_ERROR);
        }

        return trim($m[1]);
    }

    private function search(string $sid, string $nip): string
    {
        $params = '<root><dane><pParametryWyszukiwania><ParametryWyszukiwania>'
            .'<Nip>'.$nip.'</Nip>'
            .'</ParametryWyszukiwania></pParametryWyszukiwania></dane></root>';

        $body = $this->envelope(
            'DaneSzukajPodmioty',
            '<pIdentyfikatorSesji>'.htmlspecialchars($sid, ENT_XML1).'</pIdentyfikatorSesji>'
            .'<pParametryWyszukiwania>'.htmlspecialchars($params, ENT_XML1).'</pParametryWyszukiwania>'
        );

        return $this->call('DaneSzukajPodmioty', $body);
    }

    private function logout(string $sid): void
    {
        try {
            $body = $this->envelope('Wyloguj', '<pIdentyfikatorSesji>'.htmlspecialchars($sid, ENT_XML1).'</pIdentyfikatorSesji>');
            $this->call('Wyloguj', $body);
        } catch (ProviderException) {
            // A failed logout must not mask the lookup result.
        }
    }

    private function envelope(string $operation, string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:bir="'.self::NS.'">'
            .'<soapenv:Header/>'
            .'<soapenv:Body><bir:'.$operation.'>'.$inner.'</bir:'.$operation.'></soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    private function call(string $operation, string $body): string
    {
        try {
            $response = Http::timeout($this->requestTimeout)
                ->connectTimeout($this->connectTimeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"'.self::NS.'/IUslugaBIR/'.$operation.'"',
                ])
                ->withBody($body, 'text/xml')
                ->send('POST', $this->endpoint);
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

        return 'unknown';
    }

    private function parse(string $xml): ?CompanyData
    {
        // DaneSzukajPodmiotyResult is base64-encoded XML (BIR1); a change in
        // the register may also hand it over raw. Decode when it looks encoded.
        if (preg_match('#<DaneSzukajPodmiotyResult[^>]*>(.*?)</DaneSzukajPodmiotyResult>#s', $xml, $m)) {
            $payload = trim($m[1]);
            if ($payload !== '' && ! str_starts_with($payload, '<')) {
                $decoded = base64_decode($payload, true);
                if ($decoded !== false) {
                    $payload = $decoded;
                }
            }
        } else {
            $payload = $xml;
        }

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
