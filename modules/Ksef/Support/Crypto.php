<?php

namespace Modules\Ksef\Support;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\UnableToDecodeException;

/**
 * Cryptographic primitives for KSeF 2.0.
 *
 * KSeF requires RSA-OAEP with SHA-256 (and MGF1 SHA-256), which PHP's
 * openssl extension cannot produce natively (it is fixed to SHA-1). OAEP
 * SHA-256 is therefore done in pure PHP via phpseclib, with no shelling out
 * to the openssl CLI and no temp files.
 */
class Crypto
{
    /**
     * Encrypt data with the given public key using RSA-OAEP SHA-256 (MGF1).
     *
     * @return string raw ciphertext bytes
     */
    public static function rsaOaepSha256(string $data, string $publicKeyPem): string
    {
        // The KSeF API returns the certificate as base64 DER (no PEM headers).
        // Normalise to a PEM certificate before extracting the public key.
        $pem = $publicKeyPem;
        if (! str_contains($pem, '-----BEGIN')) {
            $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split($pem, 64, "\n")."-----END CERTIFICATE-----\n";
        }

        try {
            $rsa = PublicKeyLoader::load($pem);
        } catch (UnableToDecodeException $e) {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed')." (public key parse: {$e->getMessage()})");
        }

        if (! $rsa instanceof RSA) {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').' (key is not RSA)');
        }

        $encrypted = $rsa
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->encrypt($data);

        if ($encrypted === '') {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').' (empty ciphertext)');
        }

        return $encrypted;
    }

    /**
     * AES-256-CBC with PKCS#7 padding (openssl_encrypt default).
     *
     * @return string raw ciphertext bytes
     */
    public static function aes256Cbc(string $data, string $key, string $iv): string
    {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').' (AES: '.openssl_error_string().')');
        }

        return $encrypted;
    }

    /** Generate a random 256-bit (32-byte) key. */
    public static function randomKey(): string
    {
        return random_bytes(32);
    }

    /** Generate a random 128-bit (16-byte) IV. */
    public static function randomIv(): string
    {
        return random_bytes(16);
    }

    /** SHA-256 of data, base64-encoded. */
    public static function sha256Base64(string $data): string
    {
        return base64_encode(hash('sha256', $data, true));
    }
}
