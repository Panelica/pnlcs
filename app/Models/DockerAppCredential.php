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

    /** service_id + container_id => row, for drawing a whole list in one query. */
    public static function forService(int $serviceId): array
    {
        return static::where('service_id', $serviceId)->get()->keyBy('container_id')->all();
    }
}
