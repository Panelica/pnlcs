<?php

namespace Modules\Servers\Proxmox;

/**
 * One answer from the Proxmox API.
 *
 * Proxmox puts the reason for a refusal in the HTTP status line ("Permission
 * check failed (/vms/101, VM.Allocate)") and parameter problems in an
 * "errors" object; the body's data is null either way. Both end up in error.
 */
final class ProxmoxResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly mixed $data = null,
        public readonly string $error = '',
    ) {}

    public static function failed(string $error, int $status = 0): self
    {
        return new self(false, $status, null, $error);
    }

    /** A refusal because the token lacks a privilege, not because of the request. */
    public function isPermissionDenied(): bool
    {
        return $this->status === 403 || str_contains($this->error, 'Permission check failed');
    }

    /** Proxmox answered that the thing asked about is not there. */
    public function isMissing(): bool
    {
        return ! $this->ok && (
            $this->status === 404
            || str_contains($this->error, 'does not exist')
            || str_contains($this->error, 'no such VM')
        );
    }

    public function list(): array
    {
        return is_array($this->data) ? $this->data : [];
    }
}
