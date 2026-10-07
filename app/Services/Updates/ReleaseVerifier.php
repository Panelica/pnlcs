<?php

namespace App\Services\Updates;

use RuntimeException;

/**
 * Proves a downloaded release is the one we published.
 *
 * Each release carries a small statement (version, package name, its sha256
 * and size, what it needs), signed with the PNLCS release key (ECDSA P-256,
 * SHA-256). The statement is checked against the public key in
 * config/updates.php, then the package against the statement. A package that
 * fails either check is deleted, never extracted.
 */
class ReleaseVerifier
{
    public function __construct(private readonly ?string $publicKey = null) {}

    /** @return array<string, mixed> the verified statement */
    public function statement(string $statement, string $signature, Release $release): array
    {
        $key = openssl_pkey_get_public($this->publicKey ?? (string) config('updates.public_key'));
        if ($key === false) {
            throw new RuntimeException('The release public key cannot be read.');
        }

        $raw = base64_decode(trim($signature), true);
        if ($raw === false || openssl_verify($statement, $raw, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException("The signature of release {$release->version} does not match the PNLCS release key.");
        }

        $data = json_decode($statement, true);
        if (! is_array($data) || ($data['version'] ?? null) !== (string) $release->version || ($data['package'] ?? null) !== $release->packageName()
            || ! is_string($data['sha256'] ?? null) || ! is_int($data['size'] ?? null)) {
            throw new RuntimeException("The release statement of {$release->version} does not describe this release.");
        }

        return $data;
    }

    /** @param array<string, mixed> $statement */
    public function package(string $file, array $statement): void
    {
        if (! is_file($file) || filesize($file) !== $statement['size'] || ! hash_equals($statement['sha256'], (string) hash_file('sha256', $file))) {
            throw new RuntimeException("The package {$statement['package']} does not match its signed statement.");
        }
    }
}
