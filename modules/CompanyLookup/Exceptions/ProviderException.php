<?php

namespace Modules\CompanyLookup\Exceptions;

use RuntimeException;

/**
 * A provider failed to answer. The machine-readable code maps onto the error
 * catalogue the API exposes (GUS_ERROR, MF_ERROR, CEIDG_ERROR, API_TIMEOUT,
 * RATE_LIMIT, INVALID_RESPONSE), while the human message stays for logs only.
 * NOT_CONFIGURED is a special case: the source is optional and simply has no
 * credentials yet, so it is skipped without surfacing as a warning.
 */
class ProviderException extends RuntimeException
{
    public const GUS_ERROR = 'GUS_ERROR';
    public const MF_ERROR = 'MF_ERROR';
    public const CEIDG_ERROR = 'CEIDG_ERROR';
    public const OPENBRIS_ERROR = 'OPENBRIS_ERROR';
    public const API_TIMEOUT = 'API_TIMEOUT';
    public const RATE_LIMIT = 'RATE_LIMIT';
    public const INVALID_RESPONSE = 'INVALID_RESPONSE';
    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public function __construct(
        string $message,
        private readonly string $codeName,
    ) {
        parent::__construct($message);
    }

    public function codeName(): string
    {
        return $this->codeName;
    }
}
