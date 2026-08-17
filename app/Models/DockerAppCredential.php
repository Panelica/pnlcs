<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How to reach an app the customer installed.
 *
 * The panel returns this once, at deploy time, and never again - so if it is
 * not kept here the customer is left with a running app and no address or
 * first-login for it. Encrypted at rest because some apps generate a password.
 *
 * @see database/migrations/2026_08_17_270000_create_docker_app_credentials_table.php
 */
class DockerAppCredential extends Model
{
    protected $fillable = ['service_id', 'container_id', 'container_name', 'slug', 'payload'];

    protected $casts = ['payload' => 'encrypted:array'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** The address to open, if the panel gave one. */
    public function accessUrl(): ?string
    {
        return $this->payload['access_url'] ?? null;
    }

    /**
     * Label => value pairs to show: the site address, an admin URL, a generated
     * password. Empty when the app needs no credentials of its own.
     *
     * @return array<string, string>
     */
    public function items(): array
    {
        $out = [];
        foreach ((array) ($this->payload['credentials'] ?? []) as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $out[(string) $k] = (string) $v;
            }
        }

        return $out;
    }

    /** What the panel says to do after installing, if anything. */
    public function notes(): string
    {
        return trim((string) ($this->payload['notes'] ?? ''));
    }

    /** Whether there is anything worth showing at all. */
    public function hasAnything(): bool
    {
        return $this->accessUrl() !== null || $this->items() !== [] || $this->notes() !== '';
    }

    /**
     * The stored record and what the panel says right now, as one list.
     *
     * Two sources, and neither is enough alone: the deploy-time record carries
     * the panel's own notes and only exists for apps installed through here,
     * while the live lookup describes every container but knows nothing about
     * what was said at install time. Stored values win where they overlap -
     * they were written for a person to read.
     *
     * The rows built here are never saved; they exist to draw the page.
     *
     * @param  array<string, static>  $stored  keyed by container id
     * @param  array<string, array{access_url: ?string, credentials: array<string, string>, data_path: ?string}>  $live
     * @param  array<int, array<string, mixed>>  $containers
     * @return array<string, static>
     */
    public static function withLive(array $stored, array $live, array $containers): array
    {
        $out = [];
        foreach ($containers as $c) {
            $id = (string) ($c['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $row = $stored[$id] ?? null;
            $seen = $live[$id] ?? null;
            if (! $row && ! $seen) {
                continue;
            }
            if (! $row) {
                $row = new static([
                    'container_id' => $id,
                    'container_name' => ltrim((string) ($c['name'] ?? ''), '/'),
                    'slug' => (string) ($c['template'] ?? ''),
                    'payload' => [],
                ]);
            }
            if ($seen) {
                $payload = (array) $row->payload;
                if (($payload['access_url'] ?? null) === null && $seen['access_url'] !== null) {
                    $payload['access_url'] = $seen['access_url'];
                }
                $creds = (array) ($payload['credentials'] ?? []);
                foreach ($seen['credentials'] as $k => $v) {
                    if (! array_key_exists($k, $creds)) {
                        $creds[$k] = $v;
                    }
                }
                if ($seen['data_path'] !== null && ! array_key_exists('Data directory', $creds)) {
                    $creds['Data directory'] = $seen['data_path'];
                }
                $payload['credentials'] = $creds;
                $row->payload = $payload;
            }
            $out[$id] = $row;
        }

        return $out;
    }

    /** service_id + container_id => row, for drawing a whole list in one query. */
    public static function forService(int $serviceId): array
    {
        return static::where('service_id', $serviceId)->get()->keyBy('container_id')->all();
    }
}
