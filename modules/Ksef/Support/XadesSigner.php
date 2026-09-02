<?php

namespace Modules\Ksef\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Produces an enveloped XAdES-BES signature for the KSeF 2.0 AuthTokenRequest.
 *
 * Supports both RSA and ECDSA signing keys: the certificate public-key type is
 * detected and the matching SignatureMethod is emitted. ECDSA signatures are
 * converted from DER to the raw R||S encoding XMLDSIG expects.
 */
class XadesSigner
{
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    private const SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const ECDSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';
    private const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const XMLNS = 'http://www.w3.org/2000/xmlns/';

    /**
     * Sign an AuthTokenRequest XML document with XAdES-BES.
     */
    public function sign(string $unsignedXml, string $privateKeyPem, ?string $passphrase, string $certificatePem): string
    {
        $key = $this->privateKey($privateKeyPem, $passphrase);
        $cert = $this->certificate($certificatePem);

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($unsignedXml);

        $root = $doc->documentElement;

        $signature = $doc->createElementNS(self::NS_DSIG, 'ds:Signature');
        $signature->setAttribute('Id', 'Signature');

        // QualifyingProperties is built first so its digest can be referenced.
        [$qualifyingProperties, $signedProperties] = $this->qualifyingProperties($doc, $cert);

        $signedInfo = $doc->createElementNS(self::NS_DSIG, 'ds:SignedInfo');
        $signedInfo->setAttributeNS(self::XMLNS, 'xmlns:ds', self::NS_DSIG);

        $canonicalization = $doc->createElementNS(self::NS_DSIG, 'ds:CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', self::C14N);
        $signedInfo->appendChild($canonicalization);

        $signatureMethodAlg = $this->signatureMethod($key);

        $signatureMethod = $doc->createElementNS(self::NS_DSIG, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', $signatureMethodAlg);
        $signedInfo->appendChild($signatureMethod);

        // Root reference (URI="") — enveloped, digest over the whole document
        // excluding the Signature element itself.
        $signedInfo->appendChild($this->reference($doc, '', [
            self::ENVELOPED,
            self::C14N,
        ], $this->digestOfDocument($root)));

        // SignedProperties reference.
        $signedInfo->appendChild($this->reference($doc, '#SignedProperties', [
            self::C14N,
        ], $this->digestOfDetached($signedProperties)));

        $signature->appendChild($signedInfo);

        // Re-sign now that the SignedInfo is complete with its digest values.
        $signedInfoC14n = $this->c14nDetached($signedInfo);
        openssl_sign($signedInfoC14n, $raw, $key, OPENSSL_ALGO_SHA256);
        $sigValue = $doc->createElementNS(self::NS_DSIG, 'ds:SignatureValue');
        $sigValue->nodeValue = base64_encode($this->formatSignature($raw, $key));
        $signature->appendChild($sigValue);

        // KeyInfo with the X.509 certificate.
        $keyInfo = $doc->createElementNS(self::NS_DSIG, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS(self::NS_DSIG, 'ds:X509Data');
        $x509Cert = $doc->createElementNS(self::NS_DSIG, 'ds:X509Certificate');
        $x509Cert->nodeValue = base64_encode($cert['der']);
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        // Object wrapping the QualifyingProperties.
        $object = $doc->createElementNS(self::NS_DSIG, 'ds:Object');
        $object->appendChild($qualifyingProperties);
        $signature->appendChild($object);

        $root->appendChild($signature);

        return $doc->saveXML();
    }

    /** @return array{der: string, digest: string, issuer: string, serial: string} */
    private function certificate(string $pem): array
    {
        $body = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem);
        $der = (string) base64_decode((string) $body);

        $parsed = openssl_x509_parse($pem);

        return [
            'der' => $der,
            'digest' => base64_encode(hash('sha256', $der, true)),
            'issuer' => $this->formatDn($parsed['issuer'] ?? []),
            'serial' => $this->hexToDec((string) ($parsed['serialNumber'] ?? '0')),
        ];
    }

    private function privateKey(string $pem, ?string $passphrase): \OpenSSLAsymmetricKey|string
    {
        $key = $passphrase !== null && $passphrase !== ''
            ? openssl_pkey_get_private($pem, $passphrase)
            : openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new \RuntimeException(__('messages.ksef.invalid_key'));
        }

        return $key;
    }

    /** Whether the key is an EC key (vs RSA). */
    private function isEc(\OpenSSLAsymmetricKey|string $key): bool
    {
        $details = openssl_pkey_get_details($key);

        return ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC;
    }

    /** Convert a DER ECDSA signature to raw R||S (fixed 32-byte fields). */
    private function formatSignature(string $raw, \OpenSSLAsymmetricKey|string $key): string
    {
        if (! $this->isEc($key)) {
            return $raw;
        }

        // DER SEQUENCE { INTEGER r, INTEGER s }
        $i = 1; // skip 0x30
        $len = ord($raw[$i++]);
        if ($len & 0x80) {
            $n = $len & 0x7f;
            $len = 0;
            while ($n--) {
                $len = ($len << 8) | ord($raw[$i++]);
            }
        }

        $i++; // skip 0x02
        $lenR = ord($raw[$i++]);
        $r = substr($raw, $i, $lenR);
        $i += $lenR;

        $i++; // skip 0x02
        $lenS = ord($raw[$i++]);
        $s = substr($raw, $i, $lenS);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }

    private function signatureMethod(\OpenSSLAsymmetricKey|string $key): string
    {
        return $this->isEc($key) ? self::ECDSA_SHA256 : self::RSA_SHA256;
    }

    private function qualifyingProperties(DOMDocument $doc, array $cert): array
    {
        $qp = $doc->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qp->setAttribute('Target', '#Signature');
        $qp->setAttributeNS(self::XMLNS, 'xmlns:xades', self::NS_XADES);

        $signedProps = $doc->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', 'SignedProperties');
        $signedProps->setAttributeNS(self::XMLNS, 'xmlns:xades', self::NS_XADES);
        $signedProps->setAttributeNS(self::XMLNS, 'xmlns:ds', self::NS_DSIG);

        $signedSigProps = $doc->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');

        $signingTime = $doc->createElementNS(self::NS_XADES, 'xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
        $signedSigProps->appendChild($signingTime);

        $signingCert = $doc->createElementNS(self::NS_XADES, 'xades:SigningCertificate');
        $certEl = $doc->createElementNS(self::NS_XADES, 'xades:Cert');

        $certDigest = $doc->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $digestMethod = $doc->createElementNS(self::NS_DSIG, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::SHA256);
        $digestValue = $doc->createElementNS(self::NS_DSIG, 'ds:DigestValue', $cert['digest']);
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($digestValue);
        $certEl->appendChild($certDigest);

        $issuerSerial = $doc->createElementNS(self::NS_XADES, 'xades:IssuerSerial');
        $issuerName = $doc->createElementNS(self::NS_DSIG, 'ds:X509IssuerName', $cert['issuer']);
        $serial = $doc->createElementNS(self::NS_DSIG, 'ds:X509SerialNumber', $cert['serial']);
        $issuerSerial->appendChild($issuerName);
        $issuerSerial->appendChild($serial);
        $certEl->appendChild($issuerSerial);

        $signingCert->appendChild($certEl);
        $signedSigProps->appendChild($signingCert);

        $signedProps->appendChild($signedSigProps);
        $qp->appendChild($signedProps);

        return [$qp, $signedProps];
    }

    private function reference(DOMDocument $doc, string $uri, array $transforms, string $digest): DOMElement
    {
        $ref = $doc->createElementNS(self::NS_DSIG, 'ds:Reference');
        $ref->setAttribute('URI', $uri);
        if ($uri === '#SignedProperties') {
            $ref->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        }

        $transformsEl = $doc->createElementNS(self::NS_DSIG, 'ds:Transforms');
        foreach ($transforms as $algorithm) {
            $t = $doc->createElementNS(self::NS_DSIG, 'ds:Transform');
            $t->setAttribute('Algorithm', $algorithm);
            $transformsEl->appendChild($t);
        }
        $ref->appendChild($transformsEl);

        $dm = $doc->createElementNS(self::NS_DSIG, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', self::SHA256);
        $ref->appendChild($dm);

        $dv = $doc->createElementNS(self::NS_DSIG, 'ds:DigestValue', $digest);
        $ref->appendChild($dv);

        return $ref;
    }

    /** Digest of the document root (Signature not yet appended). */
    private function digestOfDocument(DOMElement $root): string
    {
        return base64_encode(hash('sha256', $root->C14N(true, false), true));
    }

    /** Digest of a detached element (re-parented into a temp document for C14N). */
    private function digestOfDetached(DOMElement $node): string
    {
        return base64_encode(hash('sha256', $this->c14nDetached($node), true));
    }

    /** Exclusive C14N of a detached element (needs a temp document). */
    private function c14nDetached(DOMElement $node): string
    {
        $temp = new DOMDocument();
        $temp->appendChild($temp->importNode($node, true));

        return $temp->documentElement->C14N(true, false);
    }

    private function formatDn(array $issuer): string
    {
        $parts = [];
        foreach ($issuer as $k => $v) {
            $parts[] = $k.'='.$v;
        }

        return implode(', ', $parts);
    }

    private function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, "0 \t\n\r\0\x0B");
        if ($hex === '') {
            return '0';
        }
        $hex = strtoupper($hex);

        $dec = '0';
        $map = '0123456789ABCDEF';
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $dec = self::decMulAdd($dec, 16, strpos($map, $hex[$i]));
        }

        return $dec;
    }

    private static function decMulAdd(string $num, int $mul, int $add): string
    {
        $carry = $add;
        $result = '';
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $digit = (int) $num[$i] * $mul + $carry;
            $result = ($digit % 10).$result;
            $carry = intdiv($digit, 10);
        }
        while ($carry > 0) {
            $result = ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }
}
