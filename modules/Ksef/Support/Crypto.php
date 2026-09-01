<?php

namespace Modules\Ksef\Support;

/**
 * Cryptographic primitives for KSeF 2.0.
 *
 * PHP's openssl extension performs RSA-OAEP with SHA-1, but KSeF requires
 * RSA-OAEP with SHA-256 (and MGF1 SHA-256). That is delegated to the openssl
 * CLI (pkeyutl), which is present on the server; the sensitive input travels
 * through temp files, never the command line.
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

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').' (public key parse: '.openssl_error_string().')');
        }
        $details = openssl_pkey_get_details($key);
        $pubPem = (string) ($details['key'] ?? '');

        if ($pubPem === '') {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').' (empty public key PEM)');
        }

        $dir = storage_path('app/ksef');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $pub = $dir.'/rsa_pub.pem';
        $in = tempnam($dir, 'in');
        $out = tempnam($dir, 'out');

        file_put_contents($pub, $pubPem);
        file_put_contents($in, $data);
        @chmod($in, 0600);

        $cmd = sprintf(
            'openssl pkeyutl -encrypt -pubin -inkey %s -pkeyopt rsa_padding_mode:oaep -pkeyopt rsa_oaep_md:sha256 -pkeyopt rsa_mgf1_md:sha256 -in %s -out %s 2>&1',
            escapeshellarg($pub),
            escapeshellarg($in),
            escapeshellarg($out),
        );

        exec($cmd, $output, $code);

        $result = ($code === 0 && is_file($out)) ? (string) file_get_contents($out) : '';
        $error = implode("\n", $output);

        @unlink($in);
        @unlink($out);

        if ($code !== 0 || $result === '') {
            throw new \RuntimeException(__('messages.ksef.encrypt_failed').($error !== '' ? ' ('.$error.')' : ''));
        }

        return $result;
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
