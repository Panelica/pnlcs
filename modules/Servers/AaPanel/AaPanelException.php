<?php

namespace Modules\Servers\AaPanel;

use RuntimeException;

/** Only deliberately safe, credential-free messages belong in this exception. */
class AaPanelException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $outcomeUnknown = false)
    {
        parent::__construct($message);
    }
}
