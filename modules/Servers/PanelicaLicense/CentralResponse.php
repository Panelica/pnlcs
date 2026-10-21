<?php

namespace Modules\Servers\PanelicaLicense;

/**
 * One answer from Panelica Central's partner API.
 *
 * status 0 means Central was not reached at all (network, TLS, timeout).
 */
final class CentralResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly mixed $data = null,
        public readonly string $error = '',
        public readonly array $meta = [],
    ) {}

    /** A refusal that will not change by asking again with the same request. */
    public function isRefusal(): bool
    {
        return in_array($this->status, [403, 404, 409, 422], true);
    }

    public function isBalanceShort(): bool
    {
        return $this->status === 402;
    }
}
